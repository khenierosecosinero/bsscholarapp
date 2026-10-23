<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\ProgramScopeService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class StaffDocumentController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private StaffDashboardService $staffData,
        private DocumentStorageService $files,
        private ProgramScopeService $programScope,
    ) {}

    public function index(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncDocumentDeadlines($programIds);
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
            'submission_deadline' => 'nullable|date',
        ]);

        $deadline = $this->deadlineFromRequest($request);

        $type = DocumentType::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'required' => $request->boolean('required'),
            'scholarship_program_id' => $programId,
            'submission_deadline' => $deadline,
            'submissions_open' => true,
            'submissions_opened_at' => ($deadline === null || $deadline->gt(now())) ? now() : null,
        ]);

        $this->scholar->syncDocumentDeadlines([$programId]);
        $type->refresh();

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
        $this->scholar->syncDocumentDeadlines($programIds);
        $documentType->refresh();
        $search = trim((string) $request->get('search', ''));
        $statusFilter = (string) $request->get('status', 'submitted');
        $scholarScope = $this->scholarScope($programIds);
        $schoolFilter = $this->staffData->documentSchoolFilter($request, $programIds, $staff);
        $schoolOptions = $this->documentSchoolOptions($schoolFilter['options']);
        $scoped = $this->scopedDocuments($documentType, $scholarScope, $schoolFilter);

        $documents = (clone $scoped)
            ->with(['user.scholarshipProgram', 'user.scholarshipClubSchool', 'documentType', 'reviewer'])
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
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'submitted' => (clone $scoped)->whereNotNull('file_path')->where('file_path', '!=', '')->count(),
            'pending' => (clone $scoped)->whereNotNull('file_path')->whereIn('status', ['pending', 'submitted'])->count(),
            'approved' => (clone $scoped)->where('status', 'approved')->count(),
            'rejected' => (clone $scoped)->where('status', 'rejected')->count(),
            'not_submitted' => (clone $scoped)->where(function ($missing) {
                $missing->whereNull('file_path')->orWhere('file_path', '')->orWhere('status', 'not_submitted');
            })->count(),
        ];
        $programTypes = DocumentType::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->orderBy('name')
            ->get(['id', 'name']);

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
                'schoolOptions' => $schoolOptions,
                'selectedSchoolKey' => $schoolFilter['key'],
                'selectedSchoolLabel' => collect($schoolOptions)->firstWhere('key', $schoolFilter['key'])['label'] ?? 'All Schools/Universities',
                'programTypes' => $programTypes,
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
            'submission_deadline' => 'nullable|date',
        ]);

        $previousDeadline = $documentType->submission_deadline?->copy();
        $closedByDeadline = ! $documentType->submissionsAreOpen()
            && $previousDeadline
            && $previousDeadline->lte(now())
            && $documentType->submissions_closed_at
            && $documentType->submissions_closed_at->gte($previousDeadline);
        $nextDeadline = $this->deadlineFromRequest($request);

        $documentType->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'required' => $request->boolean('required'),
            'submission_deadline' => $nextDeadline,
        ]);

        if ($closedByDeadline && ($nextDeadline === null || $nextDeadline->gt(now()))) {
            $documentType->update([
                'submissions_open' => true,
                'submissions_opened_at' => now(),
            ]);
        }

        $documentType->refresh();
        $this->notifyDeadlineChange($documentType, $previousDeadline, $documentType->submission_deadline);

        return redirect()
            ->route('staff.documents.show', $documentType)
            ->with('success', "Required document \"{$documentType->name}\" updated.");
    }

    public function openSubmissions(DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        if ($documentType->submissionsAreOpen()) {
            return back()->with('success', "Submissions for {$documentType->name} are already OPEN.");
        }

        $documentType->update([
            'submissions_open' => true,
            'submissions_opened_at' => now(),
        ]);
        $documentType->refresh();

        $this->scholar->notifyDocumentWindow($documentType, 'reopened');
        $this->scholar->logActivity(Auth::user(), 'document', "Opened submissions for {$documentType->name}", [
            'document_type_id' => $documentType->id,
        ]);

        $message = $documentType->deadlineHasPassed()
            ? "Submissions for {$documentType->name} are now OPEN. The deadline has passed, so scholars can upload after you update the deadline."
            : "Submissions for {$documentType->name} are now OPEN. Scholars can upload and update this document.";

        return back()->with('success', $message);
    }

    public function closeSubmissions(DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        if (! $documentType->submissionsAreOpen()) {
            return back()->with('success', "Submissions for {$documentType->name} are already CLOSED.");
        }

        $documentType->update([
            'submissions_open' => false,
            'submissions_closed_at' => now(),
        ]);
        $documentType->refresh();

        $this->scholar->notifyDocumentWindow($documentType, 'closed');
        $this->scholar->logActivity(Auth::user(), 'document', "Closed submissions for {$documentType->name}", [
            'document_type_id' => $documentType->id,
        ]);

        return back()->with('success', "Submissions for {$documentType->name} are now CLOSED. Existing files and review statuses were not changed.");
    }

    public function destroy(DocumentType $documentType)
    {
        $this->programScope->assertDocumentTypeManagedByStaff($documentType, Auth::user());

        $name = $documentType->name;

        $documentType->documents->each(function (Document $document) {
            $this->files->deleteStoredFile($document);
        });

        $documentType->delete();

        return redirect()
            ->route('staff.documents')
            ->with('success', "Required document \"{$name}\" was removed.");
    }

    public function download(Document $document)
    {
        $this->assertManagesDocument($document);

        return $this->files->stream($document, true);
    }

    public function view(Document $document)
    {
        $this->assertManagesDocument($document);

        return $this->files->stream($document, false);
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
        $label = ucfirst($status);
        $document->refresh();

        if ($document->status === $status) {
            return back()->with('success', "{$document->documentType?->name} for {$document->user?->full_name} is already {$label}.");
        }

        $document->update([
            'status' => $status,
            'review_notes' => $status === 'rejected' ? $notes : ($notes ?: null),
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        $this->scholar->notifyDocumentReview($document, $status, $notes);

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

    private function scopedDocuments(DocumentType $documentType, \Closure $scholarScope, array $schoolFilter)
    {
        $query = Document::query()
            ->where('document_type_id', $documentType->id)
            ->whereHas('user', $scholarScope);

        if (($schoolFilter['type'] ?? 'all') !== 'all') {
            $query->whereHas('user', function ($user) use ($schoolFilter) {
                $this->staffData->applySchoolFilter($user, $schoolFilter);
            });
        }

        return $query;
    }

    /**
     * @param  list<array{key: string, label: string}>  $options
     * @return list<array{key: string, label: string}>
     */
    private function documentSchoolOptions(array $options): array
    {
        return array_map(function (array $option) {
            if ($option['key'] === 'all') {
                $option['label'] = 'All Schools/Universities';
            }

            if ($option['key'] === 'unassigned') {
                $option['label'] = 'No School Specified';
            }

            return $option;
        }, $options);
    }

    private function deadlineFromRequest(Request $request): ?Carbon
    {
        if (! $request->filled('submission_deadline')) {
            return null;
        }

        return Carbon::parse($request->input('submission_deadline'))->timezone(config('app.timezone'));
    }

    private function notifyDeadlineChange(DocumentType $type, ?Carbon $previous, ?Carbon $current): void
    {
        if ($previous?->getTimestamp() === $current?->getTimestamp()) {
            return;
        }

        $event = match (true) {
            $previous !== null && $current !== null && $current->gt($previous) => 'deadline_extended',
            $previous !== null && $current !== null => 'deadline_changed',
            $current !== null => 'deadline_set',
            default => 'deadline_removed',
        };

        $this->scholar->notifyDocumentWindow($type, $event);
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
        return $this->staffData->layoutPayload(Auth::user(), $active, $title, $subtitle, $breadcrumb);
    }
}
