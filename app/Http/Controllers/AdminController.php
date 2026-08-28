<?php

namespace App\Http\Controllers;

use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\StaffDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private AdminDashboardService $admin,
        private StaffDashboardService $staff,
    ) {}

    private function syncLocation(Request $request): string
    {
        if ($request->has('location')) {
            session(['admin_location' => $request->get('location', 'all')]);
        }

        return (string) session('admin_location', 'all');
    }

    private function scope(Request $request): array
    {
        $locationKey = $this->syncLocation($request);

        return [
            'locationKey' => $locationKey,
            'programIds' => $this->admin->resolveScope($locationKey),
            'selectedLocation' => $this->admin->selectedLocation($locationKey),
            'locations' => $this->admin->locationOptions($locationKey),
            'locationGroups' => $this->admin->locationOptionGroups($locationKey),
            'isAllLocations' => $locationKey === 'all',
        ];
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
                : 'Overall / All Locations',
        ]);
    }

    public function dashboard(Request $request)
    {
        $scope = $this->scope($request);

        return view('admin.dashboard', array_merge(
            $this->layoutData($request, 'dashboard', 'Admin Dashboard', 'Monitor system-wide performance and location-specific records.'),
            [
                'stats' => $this->admin->dashboardStats($scope['programIds']),
                'locationSummaries' => $this->admin->locationSummaries($scope['locationKey']),
            ]
        ));
    }

    public function locations(Request $request)
    {
        return view('admin.locations', array_merge(
            $this->layoutData($request, 'locations', 'Locations', 'Manage scholarship program locations and view location statistics.'),
            ['summaries' => $this->admin->locationSummaries()]
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

        return redirect()->route('admin.locations')->with('success', 'Location updated successfully.');
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:255'],
            'location_type' => ['required', 'in:province,city_municipality'],
            'province_name' => ['nullable', 'string', 'max:255'],
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

        $staffMembers = $this->admin->staffQuery($scope['programIds'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.staff', array_merge(
            $this->layoutData($request, 'staff', 'Scholar Staff', 'View scholar staff accounts and their assigned locations.'),
            ['staffMembers' => $staffMembers, 'search' => $search]
        ));
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

    public function attendance(Request $request)
    {
        $scope = $this->scope($request);
        $programIds = $scope['programIds'] ?? ScholarshipProgram::active()->pluck('id')->all();

        $attendances = $this->admin->attendanceQuery($scope['programIds'])
            ->whereNotNull('check_in')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.attendance', array_merge(
            $this->layoutData($request, 'attendance', 'Attendance', 'Review attendance records across the system.'),
            [
                'attendances' => $attendances,
                'report' => $this->staff->attendanceReport($programIds),
            ]
        ));
    }

    public function serviceHours(Request $request)
    {
        $scope = $this->scope($request);
        $programIds = $scope['programIds'] ?? ScholarshipProgram::active()->pluck('id')->all();

        return view('admin.service-hours', array_merge(
            $this->layoutData($request, 'service-hours', 'Service Hours', 'Track service hours and completion progress.'),
            ['report' => $this->staff->serviceHoursReport($programIds)]
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
                'documentTypesCount' => $this->admin->documentTypesCount(),
                'documentOverview' => $this->admin->documentOverviewStats($scope['programIds']),
            ]
        ));
    }

    public function participation(Request $request)
    {
        $scope = $this->scope($request);
        $programIds = $scope['programIds'] ?? ScholarshipProgram::active()->pluck('id')->all();

        return view('admin.participation', array_merge(
            $this->layoutData($request, 'participation', 'Participation', 'Track event participation across locations.'),
            ['report' => $this->staff->participationReport($programIds)]
        ));
    }

    public function reports(Request $request)
    {
        $scope = $this->scope($request);
        $programIds = $scope['programIds'] ?? ScholarshipProgram::active()->pluck('id')->all();

        return view('admin.reports', array_merge(
            $this->layoutData($request, 'reports', 'Reports', 'Compare locations and generate system reports.'),
            [
                'stats' => $this->admin->dashboardStats($scope['programIds']),
                'locationSummaries' => $this->admin->locationSummaries($scope['locationKey']),
                'attendanceReport' => $this->staff->attendanceReport($programIds),
                'completionReport' => $this->staff->completionReport($programIds),
                'participationReport' => $this->staff->participationReport($programIds),
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
}
