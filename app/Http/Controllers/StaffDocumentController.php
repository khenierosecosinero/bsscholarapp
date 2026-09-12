<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\ProgramScopeService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffDocumentController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private StaffDashboardService $staffData,
        private ProgramScopeService $programScope,
    ) {}

    public function index(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $scholarScope = $this->scholarScope($programIds);

        $types = DocumentType::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->withCount([
                'documents as submitted_count' => fn ($q) => $q->whereNotNull('file_path')->where('file_path', '!=', '')->whereHas('user', $scholarScope),
                'documents as pending_count' => fn ($q) => $q->whereNotNull('file_path')->whereIn('status', ['pending', 'submitted'])->whereHas('user', $scholarScope),
                'documents as approved_count' => fn ($q) => $q->where('status', 'approved')->whereHas('user', $scholarScope),
                'documents as rejected_count' => fn ($q) => $q->where('status', 'rejected')->whereHas('user', $scholarScope),
            ])
            ->orderBy('name')
            ->get();

        $docsQuery = Document::query()->whereHas('user', $scholarScope);

        $stats = [
            'types' => DocumentType::query()->whereIn('scholarship_program_id', $programIds ?: [0])->count(),
            'submitted' => (clone $docsQuery)->whereNotNull('file_path')->where('file_path', '!=', '')->count(),
            'pending' => (clone $docsQuery)->whereNotNull('file_path')->whereIn('status', ['pending', 'submitted'])->count(),
            'approved' => (clone $docsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $docsQuery)->where('status', 'rejected')->count(),
        ];

        return view('staff.documents.index', array_merge(
            $this->layoutData('documents', 'Documents', 'Post required documents and review scholar submissions.'),
            compact('types', 'search', 'stats')
        ));
    }

    public function create()
    {
        return view('staff.documents.create', $this->layoutData(
            'documents',
            'Add Required Document',
            'Create a document type that scholars must submit.',
            'Documents'
        ));
    }

    public function store(Request $request)
    {
        $staff = Auth::user();
        $programId = $staff->scholarship_program_id;

        abort_unless($programId, 403, 'Your staff account is not linked to a scholarship program.');

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'required' => 'nullable|boolean',
        ]);

        $type = DocumentType::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'required' => $request->boolean('required'),
            'scholarship_program_id' => $programId,
        ]);

        $this->scholar->provisionDocumentType($type);
        $notified = $this->scholar->notifyScholarsOfDocumentType($type);

        return redirect()
            ->route('staff.documents.show', $type)
            ->with('success', "Required document \"{$type->name}\" posted. {$notified} scholar(s) were notified.");
    }

    public function show(Request $request, DocumentType $documentType)
    {
        $staff = Auth::user();
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, $staff);
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $statusFilter = $request->get('status', 'submitted');
        $scholarScope = $this->scholarScope($programIds);

        $documents = Document::query()
            ->with(['user.scholarshipProgram', 'documentType', 'reviewer'])
            ->where('document_type_id', $documentType->id)
            ->whereHas('user', $scholarScope)
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where(function ($inner) use ($search) {
                        $inner->where('full_name', 'like', "%{$search}%")
                            ->orWhere('scholar_id', 'like', "%{$search}%");
                    });
                });
            })
            ->when($statusFilter === 'submitted', fn ($q) => $q->whereNotNull('file_path')->where('file_path', '!=', ''))
            ->when($statusFilter === 'pending', fn ($q) => $q->whereNotNull('file_path')->whereIn('status', ['pending', 'submitted']))
            ->when($statusFilter === 'approved', fn ($q) => $q->where('status', 'approved'))
            ->when($statusFilter === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->when($statusFilter === 'not_submitted', fn ($q) => $q->where(function ($missing) {
                $missing->whereNull('file_path')
                    ->orWhere('file_path', '')
                    ->orWhere('status', 'not_submitted');
            }))
            ->latest('uploaded_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'submitted' => Document::where('document_type_id', $documentType->id)->whereNotNull('file_path')->where('file_path', '!=', '')->whereHas('user', $scholarScope)->count(),
            'pending' => Document::where('document_type_id', $documentType->id)->whereNotNull('file_path')->whereIn('status', ['pending', 'submitted'])->whereHas('user', $scholarScope)->count(),
            'approved' => Document::where('document_type_id', $documentType->id)->where('status', 'approved')->whereHas('user', $scholarScope)->count(),
            'rejected' => Document::where('document_type_id', $documentType->id)->where('status', 'rejected')->whereHas('user', $scholarScope)->count(),
            'not_submitted' => Document::where('document_type_id', $documentType->id)->whereHas('user', $scholarScope)->where(function ($missing) {
                $missing->whereNull('file_path')->orWhere('file_path', '')->orWhere('status', 'not_submitted');
            })->count(),
        ];

        return view('staff.documents.show', array_merge(
            $this->layoutData(
                'documents',
                $documentType->name,
                'Review scholar submissions for this required document.',
                'Documents'
            ),
            [
                'documentType' => $documentType,
                'documents' => $documents,
                'search' => $search,
                'statusFilter' => $statusFilter,
                'counts' => $counts,
            ]
        ));
    }

    public function edit(DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        return view('staff.documents.edit', array_merge(
            $this->layoutData(
                'documents',
                'Edit '.$documentType->name,
                'Update this required document type.',
                'Documents'
            ),
            ['documentType' => $documentType]
        ));
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'required' => 'nullable|boolean',
        ]);

        $documentType->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'required' => $request->boolean('required'),
        ]);

        return redirect()
            ->route('staff.documents.show', $documentType)
            ->with('success', "Required document \"{$documentType->name}\" updated.");
    }

    public function destroy(DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        $name = $documentType->name;

        $documentType->documents()
            ->whereNotNull('file_path')
            ->each(function (Document $document) {
                if ($document->file_path) {
                    Storage::disk('public')->delete($document->file_path);
                }
            });

        $documentType->delete();

        return redirect()
            ->route('staff.documents')
            ->with('success', "Required document \"{$name}\" was removed.");
    }

    public function download(Document $document)
    {
        $this->assertManagesDocument($document);

        if (! $document->file_path || ! Storage::disk('public')->exists($document->file_path)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?? 'document'
        );
    }

    public function view(Document $document)
    {
        $this->assertManagesDocument($document);

        if (! $document->file_path || ! Storage::disk('public')->exists($document->file_path)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk('public')->response(
            $document->file_path,
            $document->original_name ?? 'document'
        );
    }

    public function updateStatus(Request $request, Document $document)
    {
        $this->assertManagesDocument($document);

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'review_notes' => 'nullable|string|max:500',
        ]);

        if (! $document->hasFile()) {
            return back()->with('error', 'This scholar has not submitted a file to review.');
        }

        $status = $data['status'];
        $notes = filled($data['review_notes'] ?? null) ? trim($data['review_notes']) : null;

        $document->update([
            'status' => $status,
            'review_notes' => $status === 'rejected' ? $notes : ($notes ?: null),
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        $this->scholar->notifyDocumentReview($document, $status, $notes);

        $label = ucfirst($status);

        return back()->with('success', "{$document->documentType?->name} for {$document->user?->full_name} marked as {$label}.");
    }

    private function assertManagesDocument(Document $document): void
    {
        $document->loadMissing('user');

        abort_unless(
            $document->user && Auth::user()->canManageScholar($document->user),
            403
        );
    }

    private function scholarScope(array $programIds): \Closure
    {
        return function ($q) use ($programIds) {
            $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]);
        };
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'document';
        $slug = $base;
        $i = 2;

        while (DocumentType::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function layoutData(string $active, string $title, string $subtitle = '', ?string $breadcrumb = null): array
    {
        $staff = Auth::user()->load('scholarshipProgram');
        $program = $staff->scholarshipProgram;
        $programIds = $this->staffData->programIds($staff);

        return [
            'staff' => $staff,
            'program' => $program,
            'programIds' => $programIds,
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle ?: ($program ? 'Managing '.$staff->locationLabel().'.' : 'Manage your assigned scholarship program.'),
            'breadcrumb' => $breadcrumb ?? $title,
            'pendingApprovalsCount' => $this->staffData->scholarsQuery($programIds)->where('status', 'pending')->count(),
        ];
    }
}
