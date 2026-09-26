<?php

namespace App\Services;

use App\Models\Document;
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
    public function __construct(private GoogleDriveService $drive)
    {
    }

    public function store(User $scholar, DocumentType $type, UploadedFile $file): Document
    {
        if (! $this->drive->isConnected()) {
            throw new RuntimeException('Google Drive is not connected. Ask an administrator to connect Drive first.');
        }

        $folderId = $this->drive->ensureScholarFolder($scholar);
        $existing = Document::query()
            ->where('user_id', $scholar->id)
            ->where('document_type_id', $type->id)
            ->first();

        $oldDriveId = $existing?->google_drive_file_id;
        $oldPath = $existing?->file_path;
        $driveName = $this->driveFileName($type, $file);

        if ($oldDriveId) {
            try {
                $this->drive->deleteFile($oldDriveId);
            } catch (Throwable $e) {
                Log::warning('Could not remove the previous Drive file before replace.', [
                    'file_id' => $oldDriveId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $uploaded = $this->drive->uploadFile($file, $folderId, $driveName);
        $path = $file->store('documents/'.$scholar->id, 'public');

        try {
            $document = Document::updateOrCreate(
                ['user_id' => $scholar->id, 'document_type_id' => $type->id],
                [
                    'file_path' => $path,
                    'google_drive_file_id' => $uploaded->getId(),
                    'google_drive_web_link' => $uploaded->getWebViewLink(),
                    'original_name' => $file->getClientOriginalName(),
                    'status' => 'pending',
                    'uploaded_at' => now(),
                    'review_notes' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Document metadata save failed after Drive upload.', [
                'drive_file_id' => $uploaded->getId(),
                'message' => $e->getMessage(),
            ]);

            try {
                $this->drive->deleteFile((string) $uploaded->getId());
            } catch (Throwable) {
            }

            Storage::disk('public')->delete($path);

            throw new RuntimeException('The document could not be saved after upload.');
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $document;
    }

    public function stream(Document $document, bool $asDownload = false): Response
    {
        if ($document->google_drive_file_id) {
            try {
                $file = $this->drive->downloadFile($document->google_drive_file_id);
                $response = response($file['contents'])
                    ->header('Content-Type', $file['mime']);

                $name = $document->original_name ?: $file['name'];

                return $asDownload
                    ? $response->header('Content-Disposition', 'attachment; filename="'.$name.'"')
                    : $response->header('Content-Disposition', 'inline; filename="'.$name.'"');
            } catch (Throwable $e) {
                Log::warning('Streaming from Google Drive failed; trying local file.', [
                    'document_id' => $document->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            return $asDownload
                ? Storage::disk('public')->download($document->file_path, $document->original_name ?? 'document')
                : Storage::disk('public')->response($document->file_path, $document->original_name ?? 'document');
        }

        abort(404, 'File not found.');
    }

    public function deleteStoredFile(Document $document): void
    {
        if ($document->google_drive_file_id) {
            try {
                $this->drive->deleteFile($document->google_drive_file_id);
            } catch (Throwable $e) {
                Log::warning('Google Drive file delete during document removal failed.', [
                    'document_id' => $document->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($document->file_path) {
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

    private function driveFileName(DocumentType $type, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $base = preg_replace('/[\\\\\/:*?"<>|]+/', '', $type->name) ?: 'Document';

        return $base.'.'.$extension;
    }
}
