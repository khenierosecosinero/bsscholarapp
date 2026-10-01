<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AdminDashboardService;
use App\Services\DocumentStorageService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private ?array $resolvedScope = null;

    public function __construct(
        private AdminDashboardService $admin,
        private StaffDashboardService $staff,
        private ScholarService $scholar,
        private AccountService $accounts,
        private DocumentStorageService $files,
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
        if ($this->resolvedScope !== null) {
            return $this->resolvedScope;
        }

        $locationKey = $this->syncLocation($request);
        $programType = $this->syncProgramType($request);

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

    private function assertStaffInScope(array $programIds, User $staffMember): void
    {
        abort_unless(
            $staffMember->scholarship_program_id
                && in_array((int) $staffMember->scholarship_program_id, array_map('intval', $programIds), true),
            403,
            'This scholar staff account is outside your current admin scope.'
        );
    }

    private function assertScholarInAdminScope(Request $request, User $scholar): void
    {
        abort_unless($scholar->isScholar(), 404);

        $scope = $this->scope($request);

        abort_unless(
            $scholar->scholarship_program_id
                && in_array((int) $scholar->scholarship_program_id, array_map('intval', $scope['programIds']), true),
            404
        );
    }

    private function assertAdminDocument(Request $request, Document $document): void
    {
        $document->loadMissing('user');

        abort_unless($document->user, 404);

        $this->assertScholarInAdminScope($request, $document->user);
    }

    private function assertEventInAdminScope(Request $request, Event $event): void
    {
        $scope = $this->scope($request);

        abort_unless(
            $event->scholarship_program_id
                && in_array((int) $event->scholarship_program_id, array_map('intval', $scope['programIds']), true),
            404
        );
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
        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));

        $scholars = $this->admin->scholarsQuery($scope['programIds'], $scope['clubIds'])
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
            compact('scholars', 'search')
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
        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));

        $staffMembers = $this->admin->visibleStaffQuery($scope['programIds'], $scope['clubIds'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $pendingStaff = $this->admin->pendingStaffQuery($scope['programIds'], $scope['clubIds'])
            ->orderBy('created_at')
            ->get();
        $approvedCount = $this->admin->approvedStaffQuery($scope['programIds'], $scope['clubIds'])->count();

        return view('admin.staff', array_merge(
            $this->layoutData($request, 'staff', 'Scholar Staff', 'Review scholar staff accounts and approve new registrations.'),
            [
                'staffMembers' => $staffMembers,
                'pendingStaff' => $pendingStaff,
                'search' => $search,
                'staffStats' => [
                    'total' => $approvedCount,
                    'pending' => $pendingStaff->count(),
                    'approved' => $approvedCount,
                    'rejected' => $this->admin->staffQuery($scope['programIds'], $scope['clubIds'])->where('status', User::STATUS_REJECTED)->count(),
                ],
            ]
        ));
    }

    public function approveStaff(Request $request, User $staffMember)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $scope = $this->scope($request);

        abort_unless($staffMember->isScholarStaff(), 404);
        abort_unless($staffMember->status === User::STATUS_PENDING, 422, 'This scholar staff account is not pending approval.');
        $this->assertStaffInScope($scope['programIds'], $staffMember);

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
        $scope = $this->scope($request);

        abort_unless($staffMember->isScholarStaff(), 404);
        abort_unless($staffMember->status === User::STATUS_PENDING, 422, 'Only pending scholar staff accounts can be rejected.');
        $this->assertStaffInScope($scope['programIds'], $staffMember);

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

    public function events(Request $request)
    {
        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));

        $events = $this->admin->eventsQuery($scope['programIds'])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        $events->getCollection()->each->syncStatusFromSchedule();

        return view('admin.events', array_merge(
            $this->layoutData($request, 'events', 'Events', 'Monitor events across all locations.'),
            compact('events', 'search')
        ));
    }

    public function showEvent(Request $request, Event $event)
    {
        $this->assertEventInAdminScope($request, $event);

        $event->load('scholarshipProgram');
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
        $scope = $this->scope($request);

        return view('admin.service-hours', array_merge(
            $this->layoutData($request, 'service-hours', 'Service Hours', 'Track service hours for the selected City or Province Scholarship Program scope.'),
            ['report' => $this->staff->serviceHoursReport($scope['programIds'], null, null, $scope['clubIds'])]
        ));
    }

    public function documents(Request $request)
    {
        $scope = $this->scope($request);

        $documents = $this->admin->documentsQuery($scope['programIds'], $scope['clubIds'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.documents', array_merge(
            $this->layoutData($request, 'documents', 'Documents', 'Monitor scholar document submissions.'),
            [
                'documents' => $documents,
                'documentTypesCount' => $this->admin->documentTypesCount($scope['programIds']),
                'documentOverview' => $this->admin->documentOverviewStats($scope['programIds'], $scope['clubIds']),
            ]
        ));
    }

    public function showScholarDocuments(Request $request, User $scholar)
    {
        $this->assertScholarInAdminScope($request, $scholar);

        $scholar->load('scholarshipProgram');

        $documents = Document::query()
            ->with('documentType')
            ->where('user_id', $scholar->id)
            ->orderByRaw("CASE WHEN (file_path IS NULL OR file_path = '') AND (google_drive_file_id IS NULL OR google_drive_file_id = '') THEN 1 ELSE 0 END")
            ->orderByDesc('uploaded_at')
            ->orderBy('id')
            ->get();

        return view('admin.scholar-documents', array_merge(
            $this->layoutData($request, 'documents', $scholar->full_name, 'Documents submitted by this scholar.'),
            compact('scholar', 'documents')
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

    public function settings()
    {
        return view('admin.settings', array_merge(
            $this->layoutData(request(), 'settings', 'Admin Settings', 'Manage your administrator account and security settings.'),
            ['admins' => User::query()->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN)->orderBy('full_name')->get()]
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

    public function sidebarBadges(Request $request)
    {
        $scope = $this->scope($request);

        return response()->json([
            'staff' => $this->admin->pendingStaffQuery($scope['programIds'])->count(),
        ]);
    }
}
