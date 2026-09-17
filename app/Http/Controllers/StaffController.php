<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AccountService;
use App\Services\AttendanceSessionService;
use App\Services\ScholarService;
use App\Services\StaffDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private StaffDashboardService $staffData,
        private AccountService $accounts,
        private AttendanceSessionService $attendanceSessions,
    ) {}

    private function layoutData(string $active, string $title, string $subtitle = '', ?string $breadcrumb = null): array
    {
        return $this->staffData->layoutPayload(Auth::user(), $active, $title, $subtitle, $breadcrumb);
    }

    public function dashboard()
    {
        $staff = Auth::user();
        abort_unless($staff->hasStaffPortalAccess(), 403);

        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncMissedCheckInsForPrograms($programIds);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);

        return view('staff.dashboard', array_merge(
            $this->layoutData('dashboard', 'Dashboard', "Welcome back, {$staff->full_name}! Here's what's happening in {$staff->locationLabel()}."),
            [
                'stats' => $this->staffData->dashboardStats($programIds),
                'pendingApprovals' => $this->staffData->pendingApprovals($programIds),
                'upcomingEvents' => $this->staffData->upcomingEvents($programIds),
                'recentActivities' => $this->staffData->recentActivities($programIds),
                'attendanceBreakdown' => $this->staffData->attendanceBreakdown($programIds),
                'hoursOverview' => $this->staffData->hoursOverview($programIds),
            ]
        ));
    }

    public function scholars(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));

        $scholars = $this->staffData->scholarsQuery($programIds)
            ->with('scholarshipProgram')
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

        $stats = $this->staffData->scholarPageStats($programIds);

        return view('staff.scholars', array_merge(
            $this->layoutData('scholars', 'Scholars', 'Scholars registered in '.$staff->locationLabel().'.'),
            compact('scholars', 'search', 'stats')
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
        $this->scholar->syncMissedCheckInsForUser($scholar);
        $this->scholar->syncCompletedEventHoursForUser($scholar);

        return view('staff.scholar-show', array_merge(
            $this->layoutData('scholars', $scholar->full_name, 'Scholar account overview'),
            [
                'scholar' => $scholar,
                'hourStats' => $this->scholar->serviceHourStats($scholar),
                'documents' => $scholar->documents()->with('documentType')->get(),
                'recentActivities' => $scholar->activities()->latest()->limit(10)->get(),
                'attendances' => $scholar->attendances()->with('event')->latest()->get(),
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
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'image.image' => 'The event image must be a photo (JPG or PNG).',
            'image.mimes' => 'The event image must be a JPG or PNG file.',
            'image.max' => 'The event image must not be larger than 5MB.',
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('event_images', 'public')
            : null;

        $event = Event::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'],
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
            'service_hours' => $data['service_hours'],
            'organizer' => $data['organizer'] ?? $staff->full_name,
            'image_path' => $imagePath,
            'status' => 'confirmed',
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
            $event->scholarship_program_id
                && in_array((int) $event->scholarship_program_id, array_map('intval', $staff->managedLocationIds()), true),
            403,
            'You can only manage events for your assigned City or Province Scholarship Program.'
        );

        $event->loadCount('registrations');
        $event->load(['registrations.user', 'attendances']);

        foreach ($event->registrations as $registration) {
            if ($registration->user) {
                $this->scholar->applyMissedCheckIn(
                    $event,
                    $registration->user,
                    $registration,
                    $event->attendances->firstWhere('user_id', $registration->user_id)
                );
            }
        }

        $event->load(['registrations.user', 'attendances']);

        $participants = $event->registrations->map(function ($registration) use ($event) {
            $attendance = $event->attendances->firstWhere('user_id', $registration->user_id);
            $failed = ($event->hasEnded() || $event->attendanceSessionClosed()) && ! $attendance?->check_in;
            $status = $failed
                ? Attendance::STATUS_FAILED_CHECK_IN
                : ($attendance?->status ?? $registration->status);

            return [
                'user' => $registration->user,
                'attendance' => $attendance,
                'status' => $status,
                'status_label' => $failed
                    ? Attendance::labelFor(Attendance::STATUS_FAILED_CHECK_IN)
                    : ($attendance ? $attendance->statusLabel() : ucfirst(str_replace('_', ' ', $registration->status))),
            ];
        });

        return view('staff.events.show', array_merge(
            $this->layoutData('events', $event->title, 'Event details and participation overview.'),
            compact('event', 'participants')
        ));
    }

    public function attendance(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));

        $this->scholar->syncMissedCheckInsForPrograms($programIds);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);

        $panel = $this->attendanceEventPanel($programIds, (int) $request->get('event', 0), $search);

        return view('staff.attendance', array_merge(
            $this->layoutData('attendance', 'Attendance', 'Select an event to review check-ins, photos, and service hours.'),
            compact('search', 'panel')
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

        $stats = $this->staffData->approvalRequestStats($programIds);

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
            ->whereIn('scholarship_program_id', $programIds ?: [0])
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

    public function changePassword(Request $request)
    {
        $this->ensurePasswordChangeIsNotRateLimited($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required' => 'New password is required.',
            'password.confirmed' => 'New password and confirm new password must match.',
            'password.different' => 'New password must be different from your current password.',
            'password.min' => 'New password must be at least 8 characters.',
            'password.letters' => 'New password must include at least one letter.',
            'password.numbers' => 'New password must include at least one number.',
        ]);

        $staff = Auth::user();

        if (! Hash::check($validated['current_password'], $staff->password)) {
            RateLimiter::hit($this->passwordThrottleKey($request), 300);

            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $staff->updatePassword($validated['password']);

        RateLimiter::clear($this->passwordThrottleKey($request));
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully. You can now log in with your new password.');
    }

    private function passwordThrottleKey(Request $request): string
    {
        return 'staff-password-change|'.Auth::id().'|'.$request->ip();
    }

    private function ensurePasswordChangeIsNotRateLimited(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->passwordThrottleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->passwordThrottleKey($request));

            throw ValidationException::withMessages([
                'current_password' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    public function serviceHoursReports()
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);

        return view('staff.reports.service-hours', array_merge(
            $this->layoutData('service-hours-reports', 'Service Hours Reports', 'Track and analyze scholar service hours and completion status.', 'Service Hours Reports'),
            ['report' => $this->staffData->serviceHoursReport($programIds)]
        ));
    }

    public function attendanceReports()
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);

        return view('staff.reports.attendance', array_merge(
            $this->layoutData('attendance-reports', 'Attendance Reports', 'Track and analyze attendance records and participation status.', 'Attendance Reports'),
            ['report' => $this->staffData->attendanceReport($programIds)]
        ));
    }

    public function participationReports()
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);

        return view('staff.reports.participation', array_merge(
            $this->layoutData('participation-reports', 'Participation Reports', 'Track and analyze scholar event participation and engagement.', 'Participation Reports'),
            ['report' => $this->staffData->participationReport($programIds)]
        ));
    }

    public function completionReports()
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);

        return view('staff.reports.completion', array_merge(
            $this->layoutData('completion-reports', 'Completion Reports', 'Track and analyze scholar completion and achievement status.', 'Completion Reports'),
            ['report' => $this->staffData->completionReport($programIds)]
        ));
    }

    public function approveScholar(Request $request, User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canManageScholar($scholar), 403);

        if ($scholar->status !== 'pending') {
            return $this->approvalActionFailed($request, 'This scholar account is not pending approval.', 422);
        }

        $scholar->update(['status' => 'approved']);

        $this->scholar->logActivity($scholar, 'account', 'Account approved by scholar staff');
        $this->scholar->notify(
            $scholar,
            'Account Approved',
            'Your scholar account has been approved. You now have full access to all system features.',
            'system'
        );

        return $this->approvalActionSucceeded(
            $request,
            "{$scholar->full_name}'s account has been approved.",
            ['action' => 'approved', 'scholar_id' => $scholar->id]
        );
    }

    public function rejectScholar(Request $request, User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canManageScholar($scholar), 403);

        if ($scholar->status !== 'pending') {
            return $this->approvalActionFailed($request, 'Only pending scholar accounts can be rejected.', 422);
        }

        $scholarId = $scholar->id;
        $scholarName = $scholar->full_name;

        $this->accounts->permanentlyDelete($scholar);

        return $this->approvalActionSucceeded(
            $request,
            "{$scholarName}'s account has been rejected and permanently removed from the system.",
            ['action' => 'rejected', 'scholar_id' => $scholarId]
        );
    }

    private function approvalActionSucceeded(Request $request, string $message, array $extra = [])
    {
        $programIds = $this->staffData->programIds(Auth::user());
        $this->staffData->forgetPendingApprovalsCache($programIds);
        $stats = $this->staffData->approvalRequestStats($programIds);

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => true,
                'message' => $message,
                'stats' => $stats,
                'pending_count' => $stats['pending'],
            ], $extra));
        }

        return back()->with('success', $message);
    }

    private function approvalActionFailed(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], $status);
        }

        return back()->with('error', $message);
    }

    public function pendingApproval(Request $request)
    {
        $staff = Auth::user();

        if ($staff->hasStaffPortalAccess()) {
            return redirect()->route('staff.dashboard');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $staff->isStaffRejected()
            ? 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.'
            : 'Your scholar staff account is pending administrator approval. You cannot log in until your registration has been approved.';

        return redirect()
            ->route('login')
            ->with($staff->isStaffRejected() ? 'error' : 'warning', $message);
    }

    public function dismissPendingStaffModal(Request $request)
    {
        abort_unless(Auth::user()->isStaffPendingApproval(), 403);

        $request->session()->forget('show_pending_staff_approval_modal');

        return response()->noContent();
    }

    public function viewAttendancePhoto(Attendance $attendance)
    {
        $this->assertManagesAttendance($attendance);

        return $attendance->photoResponse();
    }

    public function approveAttendance(Attendance $attendance)
    {
        $this->assertManagesAttendance($attendance);

        try {
            $this->scholar->confirmParticipation($attendance);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Attendance for {$attendance->user?->full_name} was verified and service hours were approved.");
    }

    public function openAttendance(Event $event)
    {
        $this->assertManagesEvent($event);
        abort_unless(Auth::user()->hasStaffPortalAccess(), 403);

        try {
            $this->attendanceSessions->open($event, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Attendance for {$event->title} is now OPEN. Scholars can mark their attendance until you close the session.");
    }

    public function closeAttendance(Request $request, Event $event)
    {
        $this->assertManagesEvent($event);
        abort_unless(Auth::user()->hasStaffPortalAccess(), 403);

        try {
            $event = $this->attendanceSessions->close($event, Auth::user());
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = "Attendance for {$event->title} is now CLOSED. Scholars can no longer submit or modify their attendance.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'session' => $this->attendanceSessions->sessionPayload($event),
            ]);
        }

        return back()->with('success', $message);
    }

    public function rejectAttendance(Request $request, Attendance $attendance)
    {
        $this->assertManagesAttendance($attendance);

        $data = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $this->scholar->rejectParticipation(
                $attendance,
                filled($data['remarks'] ?? null) ? trim($data['remarks']) : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Attendance for {$attendance->user?->full_name} was not approved.");
    }

    private function assertManagesAttendance(Attendance $attendance): void
    {
        $attendance->loadMissing('user');

        abort_unless(
            $attendance->user && Auth::user()->canManageScholar($attendance->user),
            403
        );
    }

    private function assertManagesEvent(Event $event): void
    {
        abort_unless(
            $event->scholarship_program_id
                && in_array((int) $event->scholarship_program_id, array_map('intval', Auth::user()->managedLocationIds()), true),
            403,
            'You can only manage attendance for your assigned scholarship program.'
        );
    }

    private function attendanceEventPanel(array $programIds, int $selectedEventId, string $search = ''): array
    {
        $events = Event::query()
            ->with(['registrations.user', 'attendances.user'])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->when($search !== '' && $selectedEventId <= 0, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('registrations.user', function ($user) use ($search) {
                            $user->where('full_name', 'like', "%{$search}%")
                                ->orWhere('scholar_id', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('starts_at')
            ->get()
            ->each(function (Event $event) {
                $event->setAttribute('checked_in_count', $event->attendances->filter(fn ($attendance) => $attendance->hasCheckedIn())->count());
                $event->setAttribute('failed_count', $event->registrations->filter(function ($registration) use ($event) {
                    $attendance = $event->attendances->firstWhere('user_id', $registration->user_id);

                    return ($event->hasEnded() || $event->attendanceSessionClosed()) && ! $attendance?->hasCheckedIn();
                })->count());
            });

        $selected = $selectedEventId > 0
            ? $events->firstWhere('id', $selectedEventId)
            : null;

        $checkedIn = collect();
        $failed = collect();

        if ($selected) {
            foreach ($selected->registrations as $registration) {
                if ($registration->user) {
                    $this->scholar->applyMissedCheckIn(
                        $selected,
                        $registration->user,
                        $registration,
                        $selected->attendances->firstWhere('user_id', $registration->user_id)
                    );
                }
            }

            $selected->load(['registrations.user', 'attendances.user']);

            foreach ($selected->registrations as $registration) {
                $attendance = $selected->attendances->firstWhere('user_id', $registration->user_id);
                $row = [
                    'user' => $registration->user,
                    'attendance' => $attendance,
                ];

                if ($attendance?->hasCheckedIn()) {
                    $checkedIn->push($row);
                } elseif (
                    $selected->hasEnded()
                    || $selected->attendanceSessionClosed()
                    || $attendance?->status === Attendance::STATUS_FAILED_CHECK_IN
                    || $registration->status === EventRegistration::STATUS_FAILED_CHECK_IN
                ) {
                    $failed->push($row);
                }
            }
        }

        return [
            'events' => $events,
            'selected' => $selected,
            'checkedIn' => $checkedIn,
            'failed' => $failed,
        ];
    }
}
