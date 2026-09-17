<?php

namespace App\Http\Controllers;

use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AdminDashboardService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
                'city_municipality' => 'City Scholarship Programs',
                'province' => 'Province Scholarship Programs',
                default => 'All Program Types',
            },
            'programIds' => $this->admin->resolveAdminProgramIds($locationKey, $programType),
            'selectedLocation' => $this->admin->selectedLocation($locationKey),
            'locations' => $this->admin->locationOptions($locationKey),
            'locationGroups' => $this->admin->locationOptionGroups($locationKey, $programType),
            'isAllLocations' => $locationKey === 'all',
            'isAllProgramTypes' => $programType === 'all',
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

    private function layoutData(Request $request, string $active, string $title, string $subtitle = ''): array
    {
        $scope = $this->scope($request);

        return array_merge($scope, [
            'admin' => Auth::user(),
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle ?: 'System-wide administration for Batang Surigaonon Scholar\'s App.',
            'breadcrumb' => $title,
            'locationLabel' => $scope['selectedLocation']
                ? $scope['selectedLocation']->programLabel()
                : (($scope['programType'] ?? 'all') === 'all'
                    ? 'All Locations / All Programs'
                    : ($scope['programTypeLabel'].' — All Locations')),
            'adminSidebarBadges' => $this->admin->adminSidebarBadges($scope['programIds']),
        ]);
    }

    public function dashboard(Request $request)
    {
        $scope = $this->scope($request);

        return view('admin.dashboard', array_merge(
            $this->layoutData($request, 'dashboard', 'Admin Dashboard', 'Monitor system-wide performance and location-specific records.'),
            [
                'stats' => $this->admin->dashboardStats($scope['programIds']),
                'programGroups' => $this->admin->locationOptionGroups($scope['locationKey'], 'all'),
            ]
        ));
    }

    public function locations(Request $request)
    {
        $scope = $this->scope($request);

        $board = $this->admin->locationBoard($scope['locationKey'], $scope['programType']);

        return view('admin.locations', array_merge(
            $this->layoutData($request, 'locations', 'Locations', 'Manage City and Province Scholarship Program locations separately.'),
            $board
        ));
    }

    public function editLocation(ScholarshipProgram $location)
    {
        return view('admin.locations-edit', array_merge(
            $this->layoutData(request(), 'locations', 'Edit Location', 'Update location settings.'),
            [
                'location' => $location,
                'stats' => $this->admin->dashboardStats($location->coveredLocationIds()),
            ]
        ));
    }

    public function updateLocation(Request $request, ScholarshipProgram $location)
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location->update([
            'display_name' => $data['display_name'] ?? $location->display_name,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->admin->forgetLocationCaches();

        return redirect()->route('admin.locations')->with('success', 'Location updated successfully.');
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'in:province,city_municipality'],
            'province_name' => ['nullable', 'required_if:location_type,city_municipality', 'string', 'max:255'],
            'region_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        ScholarshipProgram::create([
            'location_name' => $data['location_name'],
            'location_type' => $data['location_type'],
            'province_name' => $data['province_name'] ?? null,
            'region_name' => $data['region_name'] ?? null,
            'display_name' => $data['display_name'] ?: $data['location_name'],
            'name' => $data['display_name'] ?: $data['location_name'],
            'slug' => ScholarshipProgram::slugForLocation($data['location_name']).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);
        $this->admin->forgetLocationCaches();

        return redirect()->route('admin.locations')->with('success', 'Location added successfully.');
    }

    public function scholars(Request $request)
    {
        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));

        $scholars = $this->admin->scholarsQuery($scope['programIds'])
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

    public function staff(Request $request)
    {
        $scope = $this->scope($request);
        $search = trim((string) $request->get('search', ''));

        $staffMembers = $this->admin->visibleStaffQuery($scope['programIds'])
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

        $pendingStaff = $this->admin->pendingStaffQuery($scope['programIds'])
            ->orderBy('created_at')
            ->get();
        $approvedCount = $this->admin->approvedStaffQuery($scope['programIds'])->count();

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
                    'rejected' => $this->admin->staffQuery($scope['programIds'])->where('status', User::STATUS_REJECTED)->count(),
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

    public function serviceHours(Request $request)
    {
        $scope = $this->scope($request);

        return view('admin.service-hours', array_merge(
            $this->layoutData($request, 'service-hours', 'Service Hours', 'Track service hours for the selected City or Province Scholarship Program scope.'),
            ['report' => $this->staff->serviceHoursReport($scope['programIds'])]
        ));
    }

    public function documents(Request $request)
    {
        $scope = $this->scope($request);

        $documents = $this->admin->documentsQuery($scope['programIds'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.documents', array_merge(
            $this->layoutData($request, 'documents', 'Documents', 'Monitor scholar document submissions.'),
            [
                'documents' => $documents,
                'documentTypesCount' => $this->admin->documentTypesCount($scope['programIds']),
                'documentOverview' => $this->admin->documentOverviewStats($scope['programIds']),
            ]
        ));
    }

    public function participation(Request $request)
    {
        $scope = $this->scope($request);

        return view('admin.participation', array_merge(
            $this->layoutData($request, 'participation', 'Participation', 'Track event participation for the selected scholarship program scope.'),
            ['report' => $this->staff->participationReport($scope['programIds'])]
        ));
    }

    public function reports(Request $request)
    {
        $scope = $this->scope($request);
        $this->admin->markReportsViewed($scope['programIds']);

        $stats = $this->admin->dashboardStats($scope['programIds']);
        $completionReport = $this->staff->completionReport($scope['programIds']);
        $participationReport = $this->staff->participationReport($scope['programIds']);

        return view('admin.reports', array_merge(
            $this->layoutData($request, 'reports', 'Reports', 'Compare City and Province Scholarship Program records separately.'),
            [
                'stats' => $stats,
                'attendanceReport' => $this->staff->attendanceReport($scope['programIds']),
                'completionReport' => $completionReport,
                'participationReport' => $participationReport,
                'reportCharts' => $scope['isAllLocations']
                    ? null
                    : $this->admin->locationReportCharts(
                        $scope['programIds'],
                        $stats,
                        $completionReport,
                        $participationReport
                    ),
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
