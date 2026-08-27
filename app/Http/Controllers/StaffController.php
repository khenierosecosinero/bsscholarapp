<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private StaffDashboardService $staffData,
        private AccountService $accounts,
    ) {}

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

    public function dashboard()
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);

        return view('staff.dashboard', array_merge(
            $this->layoutData('dashboard', 'Dashboard', "Welcome back, {$staff->full_name}! Here's what's happening in {$staff->locationLabel()}."),
            [
                'stats' => $this->staffData->dashboardStats($programIds),
                'pendingApprovals' => $this->staffData->pendingApprovals($programIds),
                'upcomingEvents' => $this->staffData->upcomingEvents($programIds),
                'recentActivities' => $this->staffData->recentActivities($programIds),
                'attendanceBreakdown' => $this->staffData->attendanceBreakdown($programIds),
            ]
        ));
    }

    public function scholars(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $locationId = (int) $request->get('location', 0);

        $scholars = $this->staffData->scholarsQuery($programIds)
            ->with('scholarshipProgram')
            ->when($locationId > 0, fn ($query) => $query->where('scholarship_program_id', $locationId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        $stats = $this->staffData->dashboardStats($programIds);
        $locationFilters = ScholarshipProgram::query()
            ->whereIn('id', $programIds ?: [0])
            ->orderBy('province_name')
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'province_name', 'location_type', 'display_name']);

        return view('staff.scholars', array_merge(
            $this->layoutData('scholars', 'Scholars', 'Scholars registered in '.$staff->locationLabel().'.'),
            compact('scholars', 'search', 'stats', 'locationFilters', 'locationId')
        ));
    }

    public function showScholar(User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless(
            $staff->canManageScholar($scholar),
            403,
            'You can only manage scholars assigned to your municipality, city, or province.'
        );

        $scholar->load('scholarshipProgram');
        $this->scholar->ensureUserDocuments($scholar);

        return view('staff.scholar-show', array_merge(
            $this->layoutData('scholars', $scholar->full_name, 'Scholar account overview'),
            [
                'scholar' => $scholar,
                'hourStats' => $this->scholar->serviceHourStats($scholar),
                'documents' => $scholar->documents()->with('documentType')->get(),
                'recentActivities' => $scholar->activities()->latest()->limit(10)->get(),
            ]
        ));
    }

    public function events(Request $request)
    {
        $staff = Auth::user()->load('scholarshipProgram');
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $statusFilter = $request->get('status', 'all');

        $baseQuery = Event::query()
            ->where(function ($q) use ($programIds) {
                $q->whereNull('scholarship_program_id');
                if ($programIds) {
                    $q->orWhereIn('scholarship_program_id', $programIds);
                }
            });

        $events = (clone $baseQuery)
            ->when($search !== '', fn ($q) => $q->where(function ($scoped) use ($search) {
                $scoped->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('starts_at')
            ->paginate(10)
            ->withQueryString();

        $now = now();
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'upcoming' => (clone $baseQuery)->where('starts_at', '>', $now)->count(),
            'ongoing' => (clone $baseQuery)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'completed' => (clone $baseQuery)->where('ends_at', '<', $now)->count(),
        ];

        return view('staff.events', array_merge(
            $this->layoutData('events', 'Events', 'Create, manage, and monitor all scholar activities and events.'),
            compact('events', 'search', 'stats', 'statusFilter')
        ));
    }

    public function createEvent()
    {
        $staff = Auth::user()->load('scholarshipProgram');

        abort_unless($staff->scholarship_program_id, 403, 'Your staff account is not linked to a scholarship program.');

        return view('staff.events.create', array_merge(
            $this->layoutData('events', 'Add Event', 'Create a new event for scholars in your scholarship program.'),
            ['program' => $staff->scholarshipProgram]
        ));
    }

    public function storeEvent(Request $request)
    {
        $staff = Auth::user()->load('scholarshipProgram');

        abort_unless($staff->scholarship_program_id, 403, 'Your staff account is not linked to a scholarship program.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'service_hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', 'in:confirmed,upcoming,pending'],
        ]);

        $event = Event::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'],
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
            'service_hours' => $data['service_hours'],
            'organizer' => $data['organizer'] ?? $staff->full_name,
            'image_url' => $data['image_url'] ?? null,
            'status' => $data['status'],
            'scholarship_program_id' => $staff->scholarship_program_id,
        ]);

        $notifiedCount = $this->scholar->notifyScholarsOfPublishedEvent($event);

        $message = "Event \"{$event->title}\" is scheduled for {$event->starts_at->format('F j, Y')} and now appears on scholar calendars and Events pages.";
        if ($notifiedCount > 0) {
            $message .= " {$notifiedCount} scholar(s) were notified in their Notifications section.";
        } else {
            $message .= ' No approved scholars are currently available to notify.';
        }

        return redirect()
            ->route('staff.calendar', [
                'year' => $event->starts_at->year,
                'month' => $event->starts_at->month,
            ])
            ->with('success', $message);
    }

    public function showEvent(Event $event)
    {
        $staff = Auth::user();

        abort_unless(
            $event->scholarship_program_id === null
                || in_array((int) $event->scholarship_program_id, array_map('intval', $staff->managedLocationIds()), true),
            403,
            'You can only manage events for your assigned municipality, city, or province.'
        );

        $event->loadCount('registrations');

        return view('staff.events.show', array_merge(
            $this->layoutData('events', $event->title, 'Event details and participation overview.'),
            compact('event')
        ));
    }

    public function attendance(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));

        $attendances = Attendance::query()
            ->with(['user.scholarshipProgram', 'event'])
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->whereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('event', fn ($e) => $e->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('staff.attendance', array_merge(
            $this->layoutData('attendance', 'Attendance', 'Monitor, review, and manage scholar attendance and service hours.'),
            compact('attendances', 'search')
        ));
    }

    public function approvalRequests(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));

        $requests = $this->staffData->scholarsQuery($programIds)
            ->with('scholarshipProgram')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->where('status', 'pending')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => $this->staffData->scholarsQuery($programIds)->count(),
            'pending' => $this->staffData->scholarsQuery($programIds)->where('status', 'pending')->count(),
            'approved' => $this->staffData->scholarsQuery($programIds)->where('status', 'approved')->count(),
        ];

        return view('staff.approval-requests', array_merge(
            $this->layoutData('approval-requests', 'Approval Requests', 'Review and process newly registered scholar accounts.'),
            compact('requests', 'search', 'stats')
        ));
    }

    public function calendar(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $monthDate = Carbon::create($year, $month, 1);
        $gridStart = $monthDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $gridEnd = $monthDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->endOfDay();

        $monthEvents = Event::query()
            ->where(function ($q) use ($programIds) {
                $q->whereNull('scholarship_program_id');
                if ($programIds) {
                    $q->orWhereIn('scholarship_program_id', $programIds);
                }
            })
            ->overlappingDates($gridStart, $gridEnd)
            ->orderBy('starts_at')
            ->get();

        return view('staff.calendar', array_merge(
            $this->layoutData('calendar', 'Calendar', 'Event dates you set are shown here and on scholar calendars.'),
            [
                'events' => $this->staffData->upcomingEvents($programIds, 20),
                'monthEvents' => $monthEvents,
                'monthDate' => $monthDate,
                'prevMonth' => $monthDate->copy()->subMonth(),
                'nextMonth' => $monthDate->copy()->addMonth(),
            ]
        ));
    }

    public function settings()
    {
        return view('staff.settings', $this->layoutData('settings', 'Settings', 'Manage system preferences and configurations.'));
    }

    public function serviceHoursReports()
    {
        return view('staff.reports.service-hours', $this->layoutData('service-hours-reports', 'Service Hours Reports', 'Track and analyze scholar service hours and completion status.', 'Service Hours Reports'));
    }

    public function attendanceReports()
    {
        return view('staff.reports.attendance', $this->layoutData('attendance-reports', 'Attendance Reports', 'Track and analyze attendance records and participation status.', 'Attendance Reports'));
    }

    public function participationReports()
    {
        return view('staff.reports.participation', $this->layoutData('participation-reports', 'Participation Reports', 'Track and analyze scholar event participation and engagement.', 'Participation Reports'));
    }

    public function completionReports()
    {
        return view('staff.reports.completion', $this->layoutData('completion-reports', 'Completion Reports', 'Track and analyze scholar completion and achievement status.', 'Completion Reports'));
    }

    public function approveScholar(User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canManageScholar($scholar), 403);
        abort_unless($scholar->status === 'pending', 422, 'This scholar account is not pending approval.');

        $scholar->update(['status' => 'approved']);

        $this->scholar->logActivity($scholar, 'account', 'Account approved by scholar staff');
        $this->scholar->notify(
            $scholar,
            'Account Approved',
            'Your scholar account has been approved. You now have full access to all system features.',
            'system'
        );

        return back()->with('success', "{$scholar->full_name}'s account has been approved.");
    }

    public function rejectScholar(User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canManageScholar($scholar), 403);
        abort_unless($scholar->status === 'pending', 422, 'Only pending scholar accounts can be rejected.');

        $scholarName = $scholar->full_name;

        $this->accounts->permanentlyDelete($scholar);

        return back()->with('success', "{$scholarName}'s account has been rejected and permanently removed from the system.");
    }
}
