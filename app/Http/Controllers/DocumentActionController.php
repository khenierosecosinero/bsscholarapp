<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Services\ScholarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentActionController extends Controller
{
    public function __construct(private ScholarService $scholar) {}

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

        $path = $request->file('file')->store("documents/{$user->id}", 'public');

        $existing = Document::where('user_id', $user->id)
            ->where('document_type_id', $type->id)
            ->first();

        $oldPath = $existing?->file_path;

        $document = Document::updateOrCreate(
            ['user_id' => $user->id, 'document_type_id' => $type->id],
            [
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'status' => 'pending',
                'uploaded_at' => now(),
                'review_notes' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]
        );

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->scholar->logActivity($user, 'document', "Document \"{$type->name}\" uploaded");
        $this->scholar->notify($user, 'Document Uploaded', "Your {$type->name} has been submitted for review.", 'documents');

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function download(Document $document)
    {
        if ($document->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?? 'document'
        );
    }
}
