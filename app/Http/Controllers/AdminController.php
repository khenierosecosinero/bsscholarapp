<?php

namespace App\Http\Controllers;

use App\Models\AcademicSetting;
use App\Models\AdminBackupEmail;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AccountAccessService;
use App\Services\AccountService;
use App\Services\AdminBackupEmailService;
use App\Services\AdminDashboardService;
use App\Services\DocumentStorageService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        private AccountAccessService $accountAccess,
        private DocumentStorageService $files,
        private AcademicSettingsService $academic,
        private AdminBackupEmailService $backupEmails,
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

        $locationKey = $request->has('location') ? $this->syncLocation($request) : null;
        $clubId = $request->integer('club') ?: null;

        if ($locationKey !== null && $locationKey !== 'all' && $locationKey !== '') {
            $ids = array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey));
            abort_unless(
                $staffMember->scholarship_program_id
                    && in_array((int) $staffMember->scholarship_program_id, $ids, true),
                403,
                'This scholar staff account is outside the selected location.'
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

        if ($locationKey !== 'all' && $locationKey !== '' && $locationKey !== null) {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey)), true),
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

        if ($locationKey !== 'all' && $locationKey !== '' && $locationKey !== null) {
            abort_unless(
                in_array($programId, array_map('intval', $this->admin->resolveGeographicProgramIds($locationKey)), true),
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
            'academicPeriod' => $this->academic->selectedPeriod(),
            'academicYearOptions' => $this->academic->yearOptions(),
            'adminAcademicYearFilter' => $this->academic->adminYearFilter(),
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
            'overallSummary' => $this->admin->overallReportSummary(),
            'programGroups' => $this->admin->locationOptionGroups($scope['locationKey'], $scope['programType']),
            'academicPeriod' => $this->academic->selectedPeriod(),
            'academicYearOptions' => $this->academic->yearOptions(),
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

        $clubId = ScholarshipClub::selectableId($clubId);

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

        $clubId = ScholarshipClub::selectableId($clubId);

        $directory = fn () => $this->admin->staffDirectoryQuery(
            $scope['locationKey'],
            $clubId
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
            $this->layoutData($request, 'staff', $staffMember->full_name, 'Scholar Staff account details and Started Year.'),
            [
                'member' => $staffMember,
                'staffStartedYearOptions' => $this->staffStartedYearOptions($staffMember),
            ]
        ));
    }

    public function updateStaffStartedYear(Request $request, User $staffMember)
    {
        abort_unless($staffMember->isScholarStaff(), 404);
        $this->assertStaffInDirectoryScope($request, $staffMember);

        $options = $this->staffStartedYearOptions($staffMember);
        $request->merge([
            'staff_started_year' => $request->filled('staff_started_year')
                ? $request->integer('staff_started_year')
                : null,
        ]);

        $validated = $request->validate([
            'staff_started_year' => ['nullable', 'integer', Rule::in(array_map('intval', array_keys($options)))],
        ], [
            'staff_started_year.in' => 'Choose a Started Year configured by the Administrator.',
        ]);

        $year = $validated['staff_started_year'] !== null
            ? (int) $validated['staff_started_year']
            : null;

        $staffMember->forceFill(['staff_started_year' => $year])->save();

        $this->scholar->logActivity(
            $staffMember,
            'account',
            $year
                ? 'Scholar staff Started Year set to AY '.$year.'–'.($year + 1).' by administrator'
                : 'Scholar staff Started Year cleared by administrator'
        );

        return redirect()
            ->route('admin.staff.show', array_merge(
                ['staffMember' => $staffMember],
                array_filter([
                    'location' => $request->get('location'),
                    'club' => $request->get('club'),
                    'search' => $request->get('search'),
                ], fn ($value) => $value !== null && $value !== '')
            ))
            ->with('success', $year
                ? "{$staffMember->full_name}'s Started Year was updated to AY {$year}–".($year + 1).'.'
                : "{$staffMember->full_name}'s Started Year was cleared.");
    }

    /**
     * @return array<int, string>
     */
    private function staffStartedYearOptions(?User $staffMember = null): array
    {
        $options = $this->academic->yearOptions();

        if ($staffMember?->staff_started_year) {
            $year = (int) $staffMember->staff_started_year;
            if (! isset($options[$year])) {
                $options[$year] = $year.'–'.($year + 1);
                ksort($options);
            }
        }

        return $options;
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

        $name = $staffMember->full_name;
        $email = $staffMember->email;
        $staffId = (int) $staffMember->id;
        $this->accounts->permanentlyDelete($staffMember);
        app(\App\Services\WorkflowMailService::class)->sendToAddress(
            $email,
            $name,
            'Scholar Staff Account Rejected',
            'Your scholar staff registration was not approved. You can register again with this email address.',
            \App\Support\OutboundAppUrl::withRoot(fn () => route('login')),
            'Go to sign in',
            'staff-rejected:'.$staffId
        );

        return $this->staffListRedirect($request)
            ->with(
                'success',
                "{$name}'s scholar staff registration was rejected and permanently removed. Their email and Scholarship Club name (if unused by other staff) can be used to register again."
            );
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

    public function blockStaff(Request $request, User $staffMember)
    {
        return $this->restrictStaff($request, $staffMember, User::STATUS_BLOCKED);
    }

    public function suspendStaff(Request $request, User $staffMember)
    {
        return $this->restrictStaff($request, $staffMember, User::STATUS_SUSPENDED);
    }

    public function reactivateStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->canRestrictAccount($staffMember), 403);
        abort_unless($staffMember->isScholarStaff(), 404);

        if (! $staffMember->isAccessRestricted()) {
            return $this->staffListRedirect($request)
                ->with('error', 'Only blocked or suspended scholar staff accounts can be reactivated.');
        }

        $this->accountAccess->reactivate($staffMember, Auth::user());

        return $this->staffListRedirect($request)
            ->with('success', "{$staffMember->full_name}'s account has been reactivated.");
    }

    private function restrictStaff(Request $request, User $staffMember, string $status)
    {
        abort_unless(Auth::user()?->canRestrictAccount($staffMember), 403);
        abort_unless($staffMember->isScholarStaff(), 404);

        if (! $staffMember->canBeRestricted()) {
            return $this->staffListRedirect($request)
                ->with('error', 'Only active scholar staff accounts can be blocked or suspended.');
        }

        if ($staffMember->status === $status) {
            return $this->staffListRedirect($request)
                ->with('error', "{$staffMember->full_name}'s account is already {$staffMember->accountStatusLabel()}.");
        }

        $this->accountAccess->restrict($staffMember, $status, Auth::user());

        $verb = $status === User::STATUS_BLOCKED ? 'blocked' : 'suspended';

        return $this->staffListRedirect($request)
            ->with('success', "{$staffMember->full_name}'s account has been {$verb}.");
    }

    public function deleteStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertManagedStaff($request, $staffMember);

        $name = $staffMember->full_name;
        $this->accounts->permanentlyDelete($staffMember);

        return $this->staffListRedirect($request)
            ->with(
                'success',
                "{$name}'s scholar staff account and associated records were permanently deleted. Their email and Scholarship Club name (if unused by other staff) can be used to register again."
            );
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

        $clubId = ScholarshipClub::selectableId($clubId);

        $completedIds = Event::completeExpiredConfirmedIds();
        if ($completedIds !== []) {
            $this->scholar->notifyScholarsOfCompletedEvents($completedIds);
        }

        $events = $this->admin->eventsDirectoryQuery(
            $scope['locationKey'],
            $clubId
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
                'scholarshipClubs' => $this->admin->scholarshipClubFilterOptions(),
            ]
        ));
    }

    public function showEvent(Request $request, Event $event)
    {
        $this->assertEventInAdminScope($request, $event);

        $event->load([
            'scholarshipProgram.clubs' => fn ($clubs) => $clubs->available()->orderBy('name'),
        ]);
        $event->syncStatusFromSchedule();

        $attendances = $event->attendances()->with('user')->get()->keyBy('user_id');
        $seen = [];
        $attendanceFinalized = $event->attendanceMayBeFinalized();

        $participants = $event->registrations()->with('user')->get()->map(function ($registration) use ($attendances, $attendanceFinalized, &$seen) {
            $user = $registration->user;

            if (! $user?->isScholar()) {
                return null;
            }

            $seen[(int) $user->id] = true;
            $attendance = $attendances->get($registration->user_id);
            $missed = (
                ($attendanceFinalized && ! $attendance?->check_in)
                || $attendance?->status === Attendance::STATUS_FAILED_CHECK_IN
                || $registration->status === EventRegistration::STATUS_FAILED_CHECK_IN
            );
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

        $clubId = ScholarshipClub::selectableId($clubId);

        $programIds = $this->admin->directoryProgramIds($scope['locationKey']);
        $clubIds = $clubId ? [$clubId] : null;
        $report = $this->staff->serviceHoursReport(
            $programIds,
            $this->academic->adminYearFilter(),
            null,
            $clubIds
        );
        $report = $this->filterServiceHoursReport($report, $search);

        return view('admin.service-hours', array_merge(
            $this->layoutData($request, 'service-hours', 'Service Hours', 'Track scholar service hours across all locations.'),
            [
                'report' => $report,
                'search' => $search,
                'selectedClubId' => $clubId,
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

        $clubId = ScholarshipClub::selectableId($clubId);

        $documentsQuery = $this->admin->documentsDirectoryQuery(
            $scope['locationKey'],
            $clubId
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

        $programIds = $this->admin->directoryProgramIds($scope['locationKey']);

        return view('admin.documents', array_merge(
            $this->layoutData($request, 'documents', 'Documents', 'Monitor scholar document submissions across all locations.'),
            [
                'documents' => $documents,
                'search' => $search,
                'selectedClubId' => $clubId,
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

        $documentsQuery = Document::query()
            ->with('documentType')
            ->where('user_id', $scholar->id);
        $this->academic->scopeDocumentsForReport($documentsQuery, $this->academic->adminYearFilter());

        $documents = $documentsQuery
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
            $this->layoutData($request, 'reports', 'Reports', 'Reports for Surigao del Norte and Surigao del Sur.'),
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

        $adminUser = Auth::user();

        return view('admin.settings', array_merge(
            $this->layoutData($request, 'settings', 'Admin Settings', 'Manage your administrator account, academic year, and security settings.'),
            [
                'admins' => User::query()->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN)->orderBy('full_name')->get(),
                'backupEmails' => $this->backupEmails->listFor($adminUser),
                'defaultBackupEmail' => User::PERMANENT_ADMIN_BACKUP_EMAIL,
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

    public function storeBackupEmail(Request $request)
    {
        $validated = $request->validate([
            'backup_email' => ['required', 'email', 'max:255'],
        ]);

        $this->backupEmails->add(Auth::user(), $validated['backup_email']);

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Backup email added. A verification link was sent — recovery stays disabled for that address until it is verified.');
    }

    public function updateBackupEmail(Request $request, AdminBackupEmail $backupEmail)
    {
        abort_unless((int) $backupEmail->user_id === (int) Auth::id(), 403);

        $validated = $request->validate([
            'backup_email' => ['required', 'email', 'max:255'],
        ]);

        $this->backupEmails->update(Auth::user(), $backupEmail, $validated['backup_email']);

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Backup email saved. If the address changed, a new verification link was sent.');
    }

    public function resendBackupEmailVerification(Request $request, AdminBackupEmail $backupEmail)
    {
        abort_unless((int) $backupEmail->user_id === (int) Auth::id(), 403);

        $this->backupEmails->resendVerification(Auth::user(), $backupEmail);

        return redirect()
            ->route('admin.settings')
            ->with('success', 'A new verification link was sent to that backup email.');
    }

    public function destroyBackupEmail(Request $request, AdminBackupEmail $backupEmail)
    {
        abort_unless((int) $backupEmail->user_id === (int) Auth::id(), 403);

        $this->backupEmails->remove(Auth::user(), $backupEmail);

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Backup email removed.');
    }

    public function verifyBackupEmail(Request $request, AdminBackupEmail $backupEmail)
    {
        try {
            $verified = $this->backupEmails->verifyFromSignedLink(
                $backupEmail,
                (string) $request->query('token', '')
            );
        } catch (ValidationException $e) {
            $message = $e->errors()['backup_email'][0]
                ?? 'This backup email verification link is invalid or has expired.';

            return redirect()
                ->route('login')
                ->with('error', $message);
        }

        $destination = Auth::check() && Auth::id() === (int) $verified->user_id
            ? route('admin.settings')
            : route('login');

        return redirect()
            ->to($destination)
            ->with('success', 'Backup recovery email verified. You can use it with Forgot Password if you need to reset the administrator password.');
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
