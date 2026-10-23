<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentStorageService
{
    public function __construct(
        private LocalArchive $archive,
        private AcademicSettingsService $academic,
    ) {}

    public function store(User $scholar, DocumentType $type, UploadedFile $file): Document
    {
        $existing = Document::query()
            ->where('user_id', $scholar->id)
            ->where('document_type_id', $type->id)
            ->first();

        [$yearStart, $yearEnd] = $this->resolveAcademicYear($scholar, $existing);
        $directory = $this->archive->documentDirectory($scholar, $yearStart, $yearEnd);
        $oldPath = $existing?->file_path;
        $preservingRejection = $existing?->status === 'rejected';
        $revision = $preservingRejection ? $this->archiveRejectedCopy($existing) : null;

        try {
            $path = $this->archive->storeUpload($file, $directory);
        } catch (Throwable $e) {
            $revision?->delete();
            Log::error('Document could not be stored on the local public disk.', [
                'user_id' => $scholar->id,
                'document_type_id' => $type->id,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('The document could not be saved. Please try again.');
        }

        try {
            $document = Document::updateOrCreate(
                ['user_id' => $scholar->id, 'document_type_id' => $type->id],
                [
                    'academic_year_start' => $yearStart,
                    'academic_year_end' => $yearEnd,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'status' => 'pending',
                    'uploaded_at' => now(),
                    'review_notes' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                ]
            );
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            $revision?->delete();

            throw new RuntimeException('The document could not be saved after upload.');
        }

        if (! $preservingRejection && $oldPath && $oldPath !== $path && str_starts_with($oldPath, 'bssa/')) {
            Storage::disk('public')->delete($oldPath);
        }

        return $document;
    }

    private function archiveRejectedCopy(Document $existing): ?DocumentRevision
    {
        if (! $existing->hasFile() && ! filled($existing->review_notes)) {
            return null;
        }

        return $existing->revisions()->create([
            'file_path' => $existing->file_path,
            'google_drive_file_id' => $existing->google_drive_file_id,
            'google_drive_web_link' => $existing->google_drive_web_link,
            'original_name' => $existing->original_name,
            'status' => $existing->status,
            'review_notes' => $existing->review_notes,
            'uploaded_at' => $existing->uploaded_at,
            'reviewed_at' => $existing->reviewed_at,
            'reviewed_by' => $existing->reviewed_by,
            'replaced_at' => now(),
        ]);
    }

    public function stream(Document $document, bool $asDownload = false): Response
    {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            $name = $document->original_name ?: 'document';

            return $asDownload
                ? Storage::disk('public')->download($document->file_path, $name)
                : Storage::disk('public')->response($document->file_path, $name);
        }

        abort(404, 'File not found.');
    }

    public function deleteStoredFile(Document $document): void
    {
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->update([
            'file_path' => null,
            'google_drive_file_id' => null,
            'google_drive_web_link' => null,
            'original_name' => null,
            'status' => 'not_submitted',
            'uploaded_at' => null,
            'review_notes' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveAcademicYear(User $scholar, ?Document $existing = null): array
    {
        $start = (int) ($existing?->academic_year_start ?? 0);
        $end = (int) ($existing?->academic_year_end ?? 0);

        if ($start >= 2000) {
            return [$start, $end === $start + 1 ? $end : $start + 1];
        }

        $period = $this->academic->forUser($scholar);

        return [(int) $period->year_start, (int) $period->year_end];
    }
}
