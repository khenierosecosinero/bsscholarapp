<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Services\DocumentStorageService;
use App\Services\ScholarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DocumentActionController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private DocumentStorageService $files,
    ) {}

    public function upload(Request $request)
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();
        $type = DocumentType::findOrFail($request->document_type_id);

        abort_unless(
            $type->scholarship_program_id && (int) $type->scholarship_program_id === (int) $user->scholarship_program_id,
            403,
            'This document requirement belongs to a different scholarship program.'
        );

        $existing = Document::query()
            ->where('user_id', $user->id)
            ->where('document_type_id', $type->id)
            ->first();

        abort_if($existing?->status === 'approved', 403, 'Approved documents cannot be replaced.');

        try {
            $this->files->store($user, $type, $request->file('file'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->scholar->logActivity($user, 'document', "Document \"{$type->name}\" uploaded");
        $this->scholar->notify($user, 'Document Uploaded', "Your {$type->name} has been submitted for review.", 'documents');

        return back()->with('success', $existing?->hasFile()
            ? 'Document replaced successfully.'
            : 'Document uploaded successfully.');
    }

    public function view(Document $document)
    {
        $this->assertOwnDocument($document);

        return $this->files->stream($document, false);
    }

    public function download(Document $document)
    {
        $this->assertOwnDocument($document);

        return $this->files->stream($document, true);
    }

    public function destroy(Document $document)
    {
        $this->assertOwnDocument($document);

        abort_if($document->status === 'approved', 403, 'Approved documents cannot be deleted.');

        if (! $document->hasFile()) {
            return back()->with('error', 'There is no file to delete.');
        }

        $typeName = $document->documentType?->name ?? 'Document';

        try {
            $this->files->deleteStoredFile($document);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->scholar->logActivity(Auth::user(), 'document', "Document \"{$typeName}\" deleted");

        return back()->with('success', 'Document deleted successfully.');
    }

    private function assertOwnDocument(Document $document): void
    {
        abort_unless($document->user_id === Auth::id(), 403);
    }
}
