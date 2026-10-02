<?php

namespace App\Http\Controllers;

use App\Models\AcademicSetting;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AccountService;
use App\Services\AdminDashboardService;
use App\Services\DocumentStorageService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use App\Support\PhilippineIslandGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private ?array $resolvedScope = null;

    private ?string $resolvedScopeKey = null;

    public function __construct(
        private AdminDashboardService $admin,
        private StaffDashboardService $staff,
        private ScholarService $scholar,
        private AccountService $accounts,
        private DocumentStorageService $files,
        private AcademicSettingsService $academic,
    ) {}

    private function syncLocation(Request $request): string
    {
        if ($request->has('location')) {
            session(['admin_location' => $request->get('location', 'all')]);
        }

        return (string) session('admin_location', 'all');
    }

    private function syncProgramType(Request $request): string
    {
        if ($request->has('program_type')) {
            session(['admin_program_type' => $this->admin->syncProgramType($request->get('program_type'))]);
        }

        return $this->admin->syncProgramType((string) session('admin_program_type', 'all'));
    }

    private function scope(Request $request): array
    {
        $locationKey = $this->syncLocation($request);
        $programType = $this->syncProgramType($request);
        $cacheKey = $locationKey.'|'.$programType;

        if ($this->resolvedScope !== null && $this->resolvedScopeKey === $cacheKey) {
            return $this->resolvedScope;
        }

        $this->resolvedScopeKey = $cacheKey;

        return $this->resolvedScope = [
            'locationKey' => $locationKey,
            'programType' => $programType,
            'programTypeLabel' => match ($programType) {
                'province' => 'Province Scholar',
                default => 'City Scholar',
            },
            'programIds' => $this->admin->resolveAdminProgramIds($locationKey, $programType),
            'selectedLocation' => $this->admin->selectedLocation($locationKey),
            'selectedAddress' => $this->admin->selectedAddress($locationKey),
            'locationTree' => ScholarshipProgram::locationTree(false),
            'scopedClubs' => $this->admin->clubsForAdminLocation($locationKey, $programType),
            'clubIds' => $this->admin->clubIdsForAdminQueries($locationKey, $programType),
            'locations' => $this->admin->locationOptions($locationKey),
            'locationGroups' => $this->admin->locationOptionGroups($locationKey, $programType),
            'isAllLocations' => $locationKey === 'all',
            'isAllProgramTypes' => false,
        ];
    }

    private function assertStaffInDirectoryScope(Request $request, User $staffMember): void
    {
        abort_unless($staffMember->isScholarStaff(), 404);

        $scope = $this->scope($request);
        $locationKey = $scope['locationKey'];
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if ($locationKey !== 'all' && $locationKey !== '' && $locationKey !== null) {
            $ids = array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey));
            abort_unless(
                $staffMember->scholarship_program_id
                    && in_array((int) $staffMember->scholarship_program_id, $ids, true),
                403,
                'This scholar staff account is outside the selected location.'
            );
        }

        if (PhilippineIslandGroup::isValid($region)) {
            $ids = array_map('intval', $this->admin->programIdsForIsland($region));
            abort_unless(
                $staffMember->scholarship_program_id
                    && in_array((int) $staffMember->scholarship_program_id, $ids, true),
                403,
                'This scholar staff account is outside the selected region.'
            );
        }

        if ($clubId) {
            abort_unless((int) $staffMember->scholarship_club_id === $clubId, 403, 'This scholar staff account is not assigned to the selected Scholarship Club.');
        }
    }

    private function assertScholarInAdminScope(Request $request, User $scholar): void
    {
        abort_unless($scholar->isScholar(), 404);

        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $programId = (int) $scholar->scholarship_program_id;
        abort_unless($programId > 0, 404);

        $locationKey = $this->syncLocation($request);
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($locationKey !== 'all' && $locationKey !== '' && $locationKey !== null) {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey)), true),
                404
            );
        }

        if ($region !== '') {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->programIdsForIsland($region)), true),
                404
            );
        }

        if ($clubId) {
            abort_unless((int) $scholar->scholarship_club_id === $clubId, 404);
        }
    }

    private function assertAdminDocument(Request $request, Document $document): void
    {
        $document->loadMissing('user');

        abort_unless($document->user, 404);

        $this->assertScholarInAdminScope($request, $document->user);
    }

    private function assertEventInAdminScope(Request $request, Event $event): void
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        abort_unless($event->scholarship_program_id, 404);

        $programId = (int) $event->scholarship_program_id;
        $locationKey = $this->syncLocation($request);
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($locationKey !== 'all' && $locationKey !== '' && $locationKey !== null) {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey)), true),
                404
            );
        }

        if ($region !== '') {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->programIdsForIsland($region)), true),
                404
            );
        }

        if ($clubId) {
            $clubProgramId = (int) (ScholarshipClub::query()->whereKey($clubId)->value('scholarship_program_id') ?? 0);
            abort_unless($clubProgramId > 0 && $programId === $clubProgramId, 404);
        }
    }

    private function layoutData(Request $request, string $active, string $title, string $subtitle = ''): array
    {
        $scope = $this->scope($request);

        return array_merge($scope, [
            'admin' => Auth::user(),
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle ?: 'System-wide administration for Batang Surigaonon Scholar\'s App.',
            'breadcrumb' => $title,
            'locationLabel' => $this->locationLabelForScope($scope),
            'adminSidebarBadges' => $this->admin->adminSidebarBadges($scope['programIds']),
        ]);
    }

    private function locationLabelForScope(array $scope): string
    {
        $type = $scope['programTypeLabel'] ?? 'City Scholar';
        $address = $scope['selectedAddress'] ?? ['province' => null, 'city' => null];

        if (! empty($address['city']) && ! empty($address['province'])) {
            return $type.' — '.$address['city'].', '.$address['province'];
        }

        if (! empty($address['province'])) {
            return $type.' — '.$address['province'];
        }

        return $type.' — All Locations';
    }

    private function dashboardLocationLabel(array $scope): string
    {
        $address = $scope['selectedAddress'] ?? ['province' => null, 'city' => null];

        if (! empty($address['city']) && ! empty($address['province'])) {
            return $address['city'].', '.$address['province'];
        }

        if (! empty($address['province'])) {
            return $address['province'];
        }

        return 'All Locations';
    }

    public function dashboard(Request $request)
    {
        $scope = $this->scope($request);

        $layout = $this->layoutData($request, 'dashboard', 'Admin Dashboard', 'Monitor system-wide performance and location-specific records.');
        $programIds = $this->admin->resolveGeographicProgramIds($scope['locationKey']);
        $clubs = $this->admin->geographicClubs($scope['locationKey']);

        return view('admin.dashboard', array_merge($layout, [
            'locationLabel' => $this->dashboardLocationLabel($scope),
            'stats' => $this->admin->dashboardStats($programIds, null, $clubs->count()),
            'programGroups' => $this->admin->locationOptionGroups($scope['locationKey'], $scope['programType']),
        ]));
    }

    public function scholars(Request $request)
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));
        $clubId = $request->integer('club') ?: null;

        if ($clubId && ! ScholarshipClub::query()->whereKey($clubId)->exists()) {
            $clubId = null;
        }

        $scholars = $this->admin->scholarDirectoryQuery($scope['locationKey'], $clubId)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.scholars', array_merge(
            $this->layoutData($request, 'scholars', 'Scholars', 'View all scholars across the system or by location.'),
            [
                'scholars' => $scholars,
                'search' => $search,
                'selectedClubId' => $clubId,
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
            ]
        ));
    }

    public function showScholar(User $scholar)
    {
        abort_unless($scholar->isScholar(), 404);

        $scholar->load(['scholarshipProgram', 'scholarshipClub']);

        return view('admin.scholar-show', array_merge(
            $this->layoutData(request(), 'scholars', $scholar->full_name, 'Personal information provided during registration and profile setup.'),
            ['scholar' => $scholar]
        ));
    }

    public function staff(Request $request)
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($clubId && ! ScholarshipClub::query()->whereKey($clubId)->exists()) {
            $clubId = null;
        }

        $directory = fn () => $this->admin->staffDirectoryQuery(
            $scope['locationKey'],
            $clubId,
            $region !== '' ? $region : null
        )->when($search !== '', function ($q) use ($search) {
            $q->where(function ($scoped) use ($search) {
                $scoped->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cellphone_number', 'like', "%{$search}%");
            });
        });

        $staffMembers = $directory()
            ->where('status', '!=', User::STATUS_PENDING)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $pendingStaff = $directory()
            ->where('status', User::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();
        $approvedCount = $directory()->where('status', User::STATUS_APPROVED)->count();
        $rejectedCount = $directory()->where('status', User::STATUS_REJECTED)->count();

        return view('admin.staff', array_merge(
            $this->layoutData($request, 'staff', 'Scholar Staff', 'Review scholar staff accounts and approve new registrations.'),
            [
                'staffMembers' => $staffMembers,
                'pendingStaff' => $pendingStaff,
                'search' => $search,
                'selectedClubId' => $clubId,
                'selectedRegion' => $region,
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
                'staffStats' => [
                    'total' => $approvedCount,
                    'pending' => $pendingStaff->count(),
                    'approved' => $approvedCount,
                    'rejected' => $rejectedCount,
                ],
            ]
        ));
    }

    public function showStaff(Request $request, User $staffMember)
    {
        abort_unless($staffMember->isScholarStaff(), 404);

        $staffMember->load(['scholarshipProgram', 'scholarshipClub']);

        return view('admin.staff-show', array_merge(
            $this->layoutData($request, 'staff', $staffMember->full_name, 'View-only information from Scholar Staff registration.'),
            ['member' => $staffMember]
        ));
    }

    public function approveStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        abort_unless($staffMember->isScholarStaff(), 404);
        abort_unless($staffMember->status === User::STATUS_PENDING, 422, 'This scholar staff account is not pending approval.');
        $this->assertStaffInDirectoryScope($request, $staffMember);

        $staffMember->update(['status' => User::STATUS_APPROVED]);
        $staffMember->activateFreshStaffEventList();

        $this->scholar->logActivity($staffMember, 'account', 'Scholar staff account approved by administrator');
        $this->scholar->notify(
            $staffMember,
            'Scholar Staff Account Approved',
            'Your scholar staff account has been approved. You now have full access to the Scholar Staff section.',
            'system'
        );

        return back()->with('success', "{$staffMember->full_name}'s scholar staff account has been approved.");
    }

    public function rejectStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        abort_unless($staffMember->isScholarStaff(), 404);
        abort_unless($staffMember->status === User::STATUS_PENDING, 422, 'Only pending scholar staff accounts can be rejected.');
        $this->assertStaffInDirectoryScope($request, $staffMember);

        $staffMember->update(['status' => User::STATUS_REJECTED]);

        $this->scholar->logActivity($staffMember, 'account', 'Scholar staff account rejected by administrator');
        $this->scholar->notify(
            $staffMember,
            'Scholar Staff Registration Rejected',
            'Your scholar staff registration has been rejected by an administrator. You cannot access the Scholar Staff section. Please contact the system administrator if you believe this was a mistake.',
            'system'
        );

        return back()->with('success', "{$staffMember->full_name}'s scholar staff registration has been rejected.");
    }

    public function activateStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertManagedStaff($request, $staffMember);

        if ($staffMember->status === User::STATUS_APPROVED) {
            return $this->staffListRedirect($request)
                ->with('success', "{$staffMember->full_name}'s scholar staff account is already active.");
        }

        $staffMember->update(['status' => User::STATUS_APPROVED]);
        $staffMember->activateFreshStaffEventList();

        $this->scholar->logActivity($staffMember, 'account', 'Scholar staff account activated by administrator');
        $this->scholar->notify(
            $staffMember,
            'Scholar Staff Account Activated',
            'Your scholar staff account has been activated. You can log in and access the Scholar Staff section.',
            'system'
        );

        return $this->staffListRedirect($request)
            ->with('success', "{$staffMember->full_name}'s scholar staff account has been activated.");
    }

    public function deactivateStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertManagedStaff($request, $staffMember);

        if ($staffMember->status === User::STATUS_INACTIVE) {
            return $this->staffListRedirect($request)
                ->with('success', "{$staffMember->full_name}'s scholar staff account is already deactivated.");
        }

        $staffMember->update(['status' => User::STATUS_INACTIVE]);

        $this->scholar->logActivity($staffMember, 'account', 'Scholar staff account deactivated by administrator');
        $this->scholar->notify(
            $staffMember,
            'Scholar Staff Account Deactivated',
            'Your scholar staff account has been deactivated. You cannot log in or access the Scholar Staff section until an administrator activates it again.',
            'system'
        );

        return $this->staffListRedirect($request)
            ->with('success', "{$staffMember->full_name}'s scholar staff account has been deactivated.");
    }

    public function deleteStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertManagedStaff($request, $staffMember);

        $name = $staffMember->full_name;
        $this->accounts->permanentlyDelete($staffMember);

        return $this->staffListRedirect($request)
            ->with('success', "{$name}'s scholar staff account has been permanently deleted.");
    }

    private function assertManagedStaff(Request $request, User $staffMember): void
    {
        abort_unless($staffMember->isScholarStaff(), 404);
        abort_unless(
            $staffMember->status !== User::STATUS_PENDING,
            422,
            'Pending scholar staff registrations must be approved or rejected first.'
        );
        $this->assertStaffInDirectoryScope($request, $staffMember);
    }

    private function staffListRedirect(Request $request)
    {
        return redirect()->route('admin.staff', array_filter([
            'region' => $request->get('region') ?: null,
            'location' => $request->get('location', 'all'),
            'club' => $request->get('club') ?: null,
            'search' => $request->get('search') ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function events(Request $request)
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($clubId && ! ScholarshipClub::query()->whereKey($clubId)->exists()) {
            $clubId = null;
        }

        $events = $this->admin->eventsDirectoryQuery(
            $scope['locationKey'],
            $clubId,
            $region !== '' ? $region : null
        )
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        $events->getCollection()->each->syncStatusFromSchedule();

        return view('admin.events', array_merge(
            $this->layoutData($request, 'events', 'Events', 'Monitor events across all locations.'),
            [
                'events' => $events,
                'search' => $search,
                'selectedClubId' => $clubId,
                'selectedRegion' => $region,
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
            ]
        ));
    }

    public function showEvent(Request $request, Event $event)
    {
        $this->assertEventInAdminScope($request, $event);

        $event->load([
            'scholarshipProgram.clubs' => fn ($clubs) => $clubs->active()->orderBy('name'),
        ]);
        $event->syncStatusFromSchedule();

        $attendances = $event->attendances()->with('user')->get()->keyBy('user_id');
        $seen = [];

        $participants = $event->registrations()->with('user')->get()->map(function ($registration) use ($event, $attendances, &$seen) {
            $user = $registration->user;

            if (! $user?->isScholar()) {
                return null;
            }

            $seen[(int) $user->id] = true;
            $attendance = $attendances->get($registration->user_id);
            $missed = ($event->hasEnded() || $event->attendanceSessionClosed()) && ! $attendance?->check_in;
            $status = $missed
                ? Attendance::STATUS_FAILED_CHECK_IN
                : ($attendance?->status ?? $registration->status);

            return [
                'user' => $user,
                'attendance' => $attendance,
                'status' => $status,
                'status_label' => $missed
                    ? Attendance::labelFor(Attendance::STATUS_FAILED_CHECK_IN)
                    : ($attendance ? $attendance->statusLabel() : ucfirst(str_replace('_', ' ', (string) $registration->status))),
            ];
        })->filter()->values();

        foreach ($attendances as $attendance) {
            $user = $attendance->user;

            if (! $user?->isScholar() || isset($seen[(int) $user->id])) {
                continue;
            }

            $participants->push([
                'user' => $user,
                'attendance' => $attendance,
                'status' => $attendance->status,
                'status_label' => $attendance->statusLabel(),
            ]);
        }

        $participants = $participants
            ->sortBy(fn (array $participant) => mb_strtolower((string) $participant['user']->full_name))
            ->values();

        return view('admin.event-show', array_merge(
            $this->layoutData($request, 'events', $event->title, 'Scholars associated with this event.'),
            compact('event', 'participants')
        ));
    }

    public function viewAttendancePhoto(Request $request, Event $event, Attendance $attendance)
    {
        $this->assertEventInAdminScope($request, $event);

        $attendance->loadMissing('user');

        abort_unless((int) $attendance->event_id === (int) $event->id, 404);
        abort_unless($attendance->user?->isScholar(), 404);
        abort_unless((int) $attendance->user_id === (int) $attendance->user->id, 404);
        abort_unless($attendance->hasApprovedPhoto(), 404);

        return $attendance->photoResponse();
    }

    public function serviceHours(Request $request)
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($clubId && ! ScholarshipClub::query()->whereKey($clubId)->exists()) {
            $clubId = null;
        }

        $programIds = $this->admin->directoryProgramIds(
            $scope['locationKey'],
            $region !== '' ? $region : null
        );
        $clubIds = $clubId ? [$clubId] : null;
        $report = $this->staff->serviceHoursReport($programIds, null, null, $clubIds);
        $report = $this->filterServiceHoursReport($report, $search);

        return view('admin.service-hours', array_merge(
            $this->layoutData($request, 'service-hours', 'Service Hours', 'Track scholar service hours across all locations.'),
            [
                'report' => $report,
                'search' => $search,
                'selectedClubId' => $clubId,
                'selectedRegion' => $region,
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
                'locationLabel' => $this->dashboardLocationLabel($scope),
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function filterServiceHoursReport(array $report, string $search): array
    {
        if ($search === '') {
            return $report;
        }

        $needle = mb_strtolower($search);
        $rows = collect($report['rows'])->filter(function (array $row) use ($needle) {
            $scholar = $row['scholar'];

            return str_contains(mb_strtolower((string) $scholar->full_name), $needle)
                || str_contains(mb_strtolower((string) $scholar->email), $needle)
                || str_contains(mb_strtolower((string) $scholar->scholar_id), $needle);
        })->values();

        $report['rows'] = $rows;
        $report['completed'] = $rows->where('status', 'Completed')->count();
        $report['in_progress'] = $rows->where('status', 'In Progress')->count();
        $report['not_started'] = $rows->where('status', 'Not Started')->count();
        $report['overview']['approved_hours'] = round((float) $rows->sum('approved'), 2);
        $report['overview']['pending_hours'] = round((float) $rows->sum('pending'), 2);

        return $report;
    }

    public function documents(Request $request)
    {
        if (! $request->has('location')) {
            $request->merge(['location' => 'all']);
        }

        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));
        $clubId = $request->integer('club') ?: null;
        $region = (string) $request->get('region', '');

        if (! PhilippineIslandGroup::isValid($region)) {
            $region = '';
        }

        if ($clubId && ! ScholarshipClub::query()->whereKey($clubId)->exists()) {
            $clubId = null;
        }

        $documentsQuery = $this->admin->documentsDirectoryQuery(
            $scope['locationKey'],
            $clubId,
            $region !== '' ? $region : null
        )->when($search !== '', function ($q) use ($search) {
            $q->where(function ($scoped) use ($search) {
                $scoped->whereHas('user', function ($user) use ($search) {
                    $user->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                })->orWhereHas('documentType', function ($type) use ($search) {
                    $type->where('name', 'like', "%{$search}%");
                });
            });
        });

        $documents = (clone $documentsQuery)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $programIds = $this->admin->directoryProgramIds(
            $scope['locationKey'],
            $region !== '' ? $region : null
        );

        return view('admin.documents', array_merge(
            $this->layoutData($request, 'documents', 'Documents', 'Monitor scholar document submissions across all locations.'),
            [
                'documents' => $documents,
                'search' => $search,
                'selectedClubId' => $clubId,
                'selectedRegion' => $region,
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
                'documentTypesCount' => $this->admin->documentTypesCount($programIds),
                'documentOverview' => $this->admin->documentOverviewFromQuery($documentsQuery),
                'locationLabel' => $this->dashboardLocationLabel($scope),
            ]
        ));
    }

    public function showScholarDocuments(Request $request, User $scholar)
    {
        $this->assertScholarInAdminScope($request, $scholar);

        $scholar->load(['scholarshipProgram', 'scholarshipClub']);

        $documents = Document::query()
            ->with('documentType')
            ->where('user_id', $scholar->id)
            ->orderByRaw("CASE WHEN (file_path IS NULL OR file_path = '') AND (google_drive_file_id IS NULL OR google_drive_file_id = '') THEN 1 ELSE 0 END")
            ->orderByDesc('uploaded_at')
            ->orderBy('id')
            ->get();

        return view('admin.scholar-documents', array_merge(
            $this->layoutData($request, 'documents', $scholar->full_name, 'Documents submitted by this scholar.'),
            compact('scholar', 'documents'),
            ['locationLabel' => $this->dashboardLocationLabel($this->scope($request))]
        ));
    }

    public function viewDocument(Request $request, Document $document)
    {
        $this->assertAdminDocument($request, $document);

        return $this->files->stream($document, false);
    }

    public function downloadDocument(Request $request, Document $document)
    {
        $this->assertAdminDocument($request, $document);

        return $this->files->stream($document, true);
    }

    public function reports(Request $request)
    {
        $scope = $this->scope($request);
        $this->admin->markReportsViewed($scope['programIds']);

        $reportContext = $this->admin->reportContext($request);

        return view('admin.reports', array_merge(
            $this->layoutData($request, 'reports', 'Reports', 'Regional reports for Luzon, Visayas, and Mindanao.'),
            $reportContext,
            [
                'reportRegions' => $this->admin->regionalReports($reportContext['reportFilter']),
            ]
        ));
    }

    public function settings(Request $request)
    {
        $editingId = $request->integer('edit') ?: null;
        $editingAcademicYear = $editingId
            ? AcademicSetting::query()->find($editingId)
            : null;

        $academicYears = $this->academic->managedYears();

        return view('admin.settings', array_merge(
            $this->layoutData($request, 'settings', 'Admin Settings', 'Manage your administrator account, academic year, and security settings.'),
            [
                'admins' => User::query()->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN)->orderBy('full_name')->get(),
                'academicYears' => $academicYears,
                'activeAcademicYear' => $this->academic->current(),
                'editingAcademicYear' => $editingAcademicYear,
                'academicYearUsage' => $this->academic->usageByYear($academicYears),
            ]
        ));
    }

    public function updateSettings(Request $request)
    {
        $admin = Auth::user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$admin->id],
        ]);

        $admin->update($data);

        return back()->with('success', 'Account information updated.');
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $admin = Auth::user();

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $admin->updatePassword($validated['password']);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }

    public function storeAcademicYear(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:32'],
        ]);

        $year = $this->academic->createYear($validated['academic_year'], Auth::user());

        return redirect()
            ->route('admin.settings')
            ->with('success', $year->periodLabel().' was added.');
    }

    public function updateAcademicYear(Request $request, AcademicSetting $academicYear)
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:32'],
        ]);

        $year = $this->academic->updateYear($academicYear, $validated['academic_year'], Auth::user());

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Academic year updated to '.$year->periodLabel().'.');
    }

    public function activateAcademicYear(AcademicSetting $academicYear)
    {
        $previous = $this->academic->current();
        $year = $this->academic->activate($academicYear, Auth::user());
        $message = $year->periodLabel().' is now the active academic year.';

        if ($previous->id !== $year->id) {
            $message .= ' '.$previous->periodLabel().' was deactivated.';
        }

        return redirect()
            ->route('admin.settings')
            ->with('success', $message);
    }

    public function deactivateAcademicYear(AcademicSetting $academicYear)
    {
        $year = $this->academic->deactivate($academicYear, Auth::user());
        $active = $this->academic->current();

        return redirect()
            ->route('admin.settings')
            ->with('success', $year->periodLabel().' was deactivated. '.$active->periodLabel().' is now active.');
    }

    public function destroyAcademicYear(AcademicSetting $academicYear)
    {
        $label = $academicYear->periodLabel();
        $this->academic->deleteYear($academicYear, Auth::user());

        return redirect()
            ->route('admin.settings')
            ->with('success', $label.' was deleted.');
    }

    public function sidebarBadges(Request $request)
    {
        $scope = $this->scope($request);

        return response()->json([
            'staff' => $this->admin->pendingStaffQuery($scope['programIds'])->count(),
        ]);
    }
}
