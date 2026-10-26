<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Support\LocationAddress;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AccountAccessService;
use App\Services\AccountService;
use App\Services\AttendanceSessionService;
use App\Services\LocalArchive;
use App\Services\ScholarService;
use App\Services\ScholarshipProgramAssignmentService;
use App\Services\StaffDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct(
        private ScholarService $scholar,
        private AcademicSettingsService $academic,
        private StaffDashboardService $staffData,
        private AccountService $accounts,
        private AccountAccessService $accountAccess,
        private AttendanceSessionService $attendanceSessions,
        private ScholarshipProgramAssignmentService $programAssignment,
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

        $address = $staff->scholarshipClubAddress();
        $subtitle = "Welcome back, {$staff->full_name}!";
        if ($address !== '') {
            $subtitle .= " Scholarship Club address: {$address}.";
        } else {
            $subtitle .= " Here's what's happening in {$staff->scholarshipClubName()}.";
        }

        return view('staff.dashboard', array_merge(
            $this->layoutData('dashboard', 'Dashboard', $subtitle),
            [
                'stats' => $this->staffData->dashboardStats($programIds, $staff),
                'pendingApprovals' => $this->staffData->pendingApprovals($programIds),
                'upcomingEvents' => $this->staffData->upcomingEvents($programIds, 3, $staff),
                'recentActivities' => $this->staffData->recentActivities($programIds),
                'attendanceBreakdown' => $this->staffData->attendanceBreakdown(
                    $programIds,
                    $this->academic->resolveReportFilter(
                        (int) $this->academic->selectedPeriod()->year_start,
                        $this->academic->selectedPeriod()->semester
                    ),
                    $staff
                ),
                'hoursOverview' => $this->staffData->hoursOverview($programIds),
            ]
        ));
    }

    public function scholars(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $schoolFilter = $this->staffData->scholarsSchoolFilter($request, $programIds, $staff);

        $scholarsQuery = $this->staffData->scholarsQuery($programIds, $staff)
            ->whereIn('status', [User::STATUS_APPROVED, User::STATUS_BLOCKED, User::STATUS_SUSPENDED])
            ->with(['scholarshipProgram', 'scholarshipClub', 'scholarshipClubSchool', 'activeSuspension.issuer'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $this->staffData->applySchoolFilter($scholarsQuery, $schoolFilter);

        $scholars = $scholarsQuery
            ->orderBy('full_name')
            ->get();

        $stats = $this->staffData->scholarPageStats($programIds, $staff);
        $stats['total_scholars'] = $this->staffData->scholarsQuery($programIds, $staff)
            ->whereIn('status', [User::STATUS_APPROVED, User::STATUS_BLOCKED, User::STATUS_SUSPENDED])
            ->count();

        return view('staff.scholars', array_merge(
            $this->layoutData('scholars', 'Scholars', 'Scholars registered in '.$staff->scholarshipClubLabel().'.'),
            [
                'scholars' => $scholars,
                'search' => $search,
                'stats' => $stats,
                'schoolOptions' => $schoolFilter['options'],
                'selectedSchoolKey' => $schoolFilter['key'],
            ]
        ));
    }

    public function scholarPresence()
    {
        $staff = Auth::user();
        abort_unless($staff->hasStaffPortalAccess(), 403);

        $programIds = $this->staffData->programIds($staff);
        $presence = [];

        $this->staffData->scholarsQuery($programIds, $staff)
            ->where('status', User::STATUS_APPROVED)
            ->get(['id', 'role', 'last_seen_at'])
            ->each(function (User $scholar) use (&$presence) {
                $presence[(string) $scholar->id] = [
                    'online' => $scholar->isPresentNow(),
                    'label' => $scholar->presenceLabel(),
                ];
            });

        return response()->json(['scholars' => $presence]);
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

        $scholar->load(['scholarshipProgram', 'scholarshipClub', 'scholarshipClubSchool', 'activeSuspension.issuer']);
        $this->scholar->ensureUserDocuments($scholar);
        $this->scholar->syncMissedCheckInsForUser($scholar);
        $this->scholar->syncCompletedEventHoursForUser($scholar);

        $hourTracking = $this->scholar->programHourTracking($scholar);

        return view('staff.scholar-show', array_merge(
            $this->layoutData('scholars', $scholar->full_name, 'Scholar account overview'),
            [
                'scholar' => $scholar,
                'hourStats' => $hourTracking['current'],
                'semesterInfo' => $hourTracking['period'],
                'hourTracking' => $hourTracking,
                'programStartYearOptions' => $this->scholar->programStartYearOptions($scholar),
                'documents' => $scholar->documents()->with('documentType')->get(),
                'recentActivities' => $scholar->activities()->latest()->limit(10)->get(),
                'attendances' => $this->academic->scopeAttendancesForPeriod(
                    $scholar->attendances()->with('event')->latest(),
                    $this->academic->selectedPeriod()
                )->get(),
            ]
        ));
    }

    public function updateScholarProgramStart(Request $request, User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless(
            $staff->canManageScholar($scholar),
            403,
            'You can only manage scholars assigned to your Scholarship Club.'
        );

        $validated = $request->validate([
            'program_start_year' => ['required', 'integer'],
        ]);

        $start = $this->scholar->setProgramStartYear($scholar, (int) $validated['program_start_year']);
        $this->scholar->logActivity($staff, 'staff', 'Scholar Start Academic Year updated', [
            'scholar_id' => $scholar->id,
            'program_start_year' => $start,
        ]);

        return redirect()
            ->route('staff.scholars.show', $scholar)
            ->with('success', 'Start Academic Year saved as AY '.$start.'–'.($start + 1).'.');
    }

    public function events(Request $request)
    {
        $staff = Auth::user()->load('scholarshipProgram');
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $statusFilter = $request->get('status', 'all');

        $baseQuery = Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->visibleToStaff($staff);

        $completedIds = Event::completeExpiredConfirmedIds();
        if ($completedIds !== []) {
            $this->scholar->notifyScholarsOfCompletedEvents($completedIds);
        }

        $events = (clone $baseQuery)
            ->when($search !== '', fn ($q) => $q->where(function ($scoped) use ($search) {
                $scoped->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
                if ($statusFilter === 'upcoming') {
                    return $q->where('status', Event::STATUS_CONFIRMED)
                        ->where('starts_at', '>', now());
                }

                if ($statusFilter === 'ongoing') {
                    return $q->whereIn('status', [Event::STATUS_CONFIRMED, Event::STATUS_ONGOING])
                        ->where('starts_at', '<=', now())
                        ->where('ends_at', '>=', now());
                }

                if ($statusFilter === Event::STATUS_CONFIRMED) {
                    return $q->whereIn('status', [Event::STATUS_CONFIRMED, Event::STATUS_ONGOING]);
                }

                return $q->where('status', $statusFilter);
            })
            ->orderByDesc('starts_at')
            ->paginate(10)
            ->withQueryString();

        $now = now();
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'upcoming' => (clone $baseQuery)
                ->where('status', Event::STATUS_CONFIRMED)
                ->where('starts_at', '>', $now)
                ->count(),
            'ongoing' => (clone $baseQuery)
                ->whereIn('status', [Event::STATUS_CONFIRMED, Event::STATUS_ONGOING])
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->count(),
            'pending' => (clone $baseQuery)->where('status', Event::STATUS_PENDING)->count(),
            'completed' => (clone $baseQuery)->where('status', Event::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $baseQuery)->where('status', Event::STATUS_CANCELLED)->count(),
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
            'ends_at.after' => 'The event end date cannot be earlier than the start date.',
            'image.image' => 'The event image must be a photo (JPG or PNG).',
            'image.mimes' => 'The event image must be a JPG or PNG file.',
            'image.max' => 'The event image must not be larger than 5MB.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $program = $staff->scholarshipProgram;
            $period = $this->academic->current();
            $imagePath = $program
                ? app(LocalArchive::class)->storeUpload(
                    $request->file('image'),
                    app(LocalArchive::class)->eventImageDirectory($program, $data['title'], (int) $period->year_start, (int) $period->year_end)
                )
                : $request->file('image')->store('event_images', 'public');
        }

        $event = Event::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'],
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
            'service_hours' => $data['service_hours'],
            'organizer' => $data['organizer'] ?? $staff->full_name,
            'image_path' => $imagePath,
            'status' => Event::STATUS_PENDING,
            'attendance_is_open' => false,
            'scholarship_program_id' => $staff->scholarship_program_id,
            'scholarship_club_id' => $staff->scholarship_club_id,
        ]);

        $notifiedCount = $this->scholar->notifyScholarsOfEventStatusChange($event, Event::STATUS_PENDING);

        $message = "Event \"{$event->title}\" is scheduled for {$event->dateRangeLabel()} with registration status Pending. It appears on scholar Events pages as Pending until you confirm it. No scholars are registered until they click Attend / Register. Attendance stays closed.";
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

    public function updateEventStatus(Request $request, Event $event)
    {
        $staff = Auth::user();

        abort_unless(
            $staff->canViewStaffManagedEvent($event),
            403,
            'You can only manage events created after your scholar staff account became active for your assigned scholarship program.'
        );

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                Event::STATUS_PENDING,
                Event::STATUS_CONFIRMED,
                Event::STATUS_CANCELLED,
            ])],
        ]);

        $target = $data['status'];

        if (! $event->canEditStaffStatus() && $event->staffStatusSelectValue() !== $target) {
            return redirect()
                ->route('staff.events')
                ->with('error', "\"{$event->title}\" status can no longer be changed.");
        }

        if (! $event->applyStaffStatus($target)) {
            return redirect()
                ->route('staff.events')
                ->with('error', "\"{$event->title}\" could not be updated to ".ucfirst($target).'.');
        }

        $event->refresh();
        $this->scholar->syncRegistrationsWithEventStatus($event);
        $this->scholar->notifyScholarsOfEventStatusChange($event, $target);

        $label = match ($target) {
            Event::STATUS_PENDING => 'Pending',
            Event::STATUS_CONFIRMED => 'Confirmed',
            Event::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($target),
        };

        return redirect()
            ->route('staff.events')
            ->with('success', "\"{$event->title}\" is now {$label}.");
    }

    public function markEventPending(Event $event)
    {
        return $this->updateEventStatus(request()->merge(['status' => Event::STATUS_PENDING]), $event);
    }

    public function markEventConfirmed(Event $event)
    {
        return $this->updateEventStatus(request()->merge(['status' => Event::STATUS_CONFIRMED]), $event);
    }

    public function cancelEvent(Event $event)
    {
        return $this->updateEventStatus(request()->merge(['status' => Event::STATUS_CANCELLED]), $event);
    }

    public function destroyEvent(Event $event)
    {
        $staff = Auth::user();

        abort_unless(
            $staff->canViewStaffManagedEvent($event),
            403,
            'You can only manage events created after your scholar staff account became active for your assigned scholarship program.'
        );

        if (! $event->canBeRemoved()) {
            return redirect()
                ->route('staff.events')
                ->with('error', "\"{$event->title}\" can only be removed after it has been cancelled.");
        }

        $title = $event->title;

        if (! $event->removeCancelled()) {
            return redirect()
                ->route('staff.events')
                ->with('error', "\"{$title}\" could not be removed. Only cancelled events can be deleted.");
        }

        return redirect()
            ->route('staff.events')
            ->with('success', "\"{$title}\" has been removed.");
    }

    public function showEvent(Event $event)
    {
        $staff = Auth::user();

        abort_unless(
            $staff->canViewStaffManagedEvent($event),
            403,
            'You can only manage events created after your scholar staff account became active for your assigned scholarship program.'
        );

        $event->syncStatusFromSchedule();

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
        $attendanceFinalized = $event->attendanceMayBeFinalized();

        $participants = $event->registrations->map(function ($registration) use ($event, $attendanceFinalized) {
            $attendance = $event->attendances->firstWhere('user_id', $registration->user_id);
            $failed = (
                ($attendanceFinalized && ! $attendance?->check_in)
                || $attendance?->status === Attendance::STATUS_FAILED_CHECK_IN
                || $registration->status === EventRegistration::STATUS_FAILED_CHECK_IN
            );
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
        $schoolFilter = $this->staffData->scholarsSchoolFilter($request, $programIds, $staff);
        $schoolOptions = $this->attendanceSchoolOptions($schoolFilter['options']);

        $this->scholar->syncMissedCheckInsForPrograms($programIds);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);

        $panel = $this->attendanceEventPanel($programIds, (int) $request->get('event', 0), $search, $schoolFilter);

        return view('staff.attendance', array_merge(
            $this->layoutData('attendance', 'Attendance', 'Select an event to review check-ins, photos, and service hours.'),
            [
                'search' => $search,
                'panel' => $panel,
                'schoolOptions' => $schoolOptions,
                'selectedSchoolKey' => $schoolFilter['key'],
                'selectedSchoolLabel' => collect($schoolOptions)->firstWhere('key', $schoolFilter['key'])['label'] ?? 'All Schools/Universities',
            ]
        ));
    }

    public function approvalRequests(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $search = trim((string) $request->get('search', ''));
        $statusFilter = (string) $request->get('status', User::STATUS_PENDING);

        if (! in_array($statusFilter, [User::STATUS_PENDING, User::STATUS_REJECTED], true)) {
            $statusFilter = User::STATUS_PENDING;
        }

        $requests = $this->staffData->scholarsQuery($programIds, $staff)
            ->with(['scholarshipProgram', 'scholarshipClub'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($scoped) use ($search) {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->where('status', $statusFilter)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $stats = $this->staffData->approvalRequestStats($programIds, $staff);

        return view('staff.approval-requests', array_merge(
            $this->layoutData('approval-requests', 'Approval Requests', 'Review and process newly registered scholar accounts.'),
            compact('requests', 'search', 'stats', 'statusFilter')
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

        $completedIds = Event::completeExpiredConfirmedIds();
        if ($completedIds !== []) {
            $this->scholar->notifyScholarsOfCompletedEvents($completedIds);
        }

        $monthEvents = Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->visibleToStaff($staff)
            ->overlappingDates($gridStart, $gridEnd)
            ->orderBy('starts_at')
            ->get();

        return view('staff.calendar', array_merge(
            $this->layoutData('calendar', 'Calendar', 'Event dates you set are shown here and on scholar calendars.'),
            [
                'events' => $this->staffData->upcomingEvents($programIds, 20, $staff),
                'monthEvents' => $monthEvents,
                'scheduleStats' => $this->staffData->scheduleSummaryStats($staff),
                'monthDate' => $monthDate,
                'prevMonth' => $monthDate->copy()->subMonth(),
                'nextMonth' => $monthDate->copy()->addMonth(),
            ]
        ));
    }

    public function settings()
    {
        $staff = Auth::user()->load(['scholarshipClub.schools']);

        return view('staff.settings', array_merge(
            $this->layoutData('settings', 'Settings', 'Manage system preferences and configurations.'),
            [
                'locationTree' => ScholarshipProgram::locationTree(),
                'schools' => $staff->scholarshipClub?->schools ?? collect(),
            ]
        ));
    }

    public function updateClubName(Request $request)
    {
        $staff = Auth::user()->load('scholarshipClub', 'scholarshipProgram');

        $request->merge([
            'scholarship_club_name' => ScholarshipClub::normalizeName((string) $request->input('scholarship_club_name', '')),
        ]);

        $validated = $request->validate([
            'scholarship_club_name' => ['required', 'string', 'max:255'],
            'scholarship_program_id' => [
                'required',
                'integer',
                Rule::exists('scholarship_programs', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('location_type', 'city_municipality')
                ),
            ],
            'cellphone_number' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
        ], [
            'scholarship_club_name.required' => 'Please enter a Scholarship Club Name.',
            'scholarship_program_id.required' => 'Please select the Province and Municipality or City where the Scholarship Club is located.',
            'scholarship_program_id.exists' => 'Please select a valid Municipality or City for the Scholarship Club address.',
        ]);

        ScholarshipClub::assertNameIsUnique(
            $validated['scholarship_club_name'],
            $staff->scholarship_club_id ? (int) $staff->scholarship_club_id : null
        );

        $assignment = $this->programAssignment->resolveRegistrationAssignment((int) $validated['scholarship_program_id']);
        $street = LocationAddress::normalizeStreet($validated['street'] ?? null);

        DB::transaction(function () use ($staff, $validated, $assignment, $street) {
            if ($staff->scholarshipClub) {
                $staff->scholarshipClub->updateDetails(
                    $validated['scholarship_club_name'],
                    (int) $assignment['scholarship_program_id'],
                    $street,
                    true
                );
            } else {
                $club = ScholarshipClub::createForProgram(
                    $validated['scholarship_club_name'],
                    (int) $assignment['scholarship_program_id'],
                    $staff->id,
                    $street
                );
                $staff->scholarship_club_id = $club->id;
            }

            $staff->scholarship_program_id = $assignment['scholarship_program_id'];
            $staff->city = $assignment['city'];
            $staff->province = $assignment['province'];
            $staff->street = $street;
            $contactNumber = trim((string) ($validated['cellphone_number'] ?? ''));
            $staff->cellphone_number = $contactNumber !== '' ? $contactNumber : null;
            $staff->save();
        });

        return back()->with('success', 'Scholarship Club details and contact number were saved.');
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

    public function serviceHoursReports(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);
        $context = $this->staffData->reportContext($request, $programIds, $staff);

        return view('staff.reports.service-hours', array_merge(
            $this->layoutData('service-hours-reports', 'Service Hours Reports', 'Track and analyze scholar service hours and completion status.', 'Service Hours Reports'),
            $context,
            ['report' => $this->staffData->serviceHoursReport($programIds, $context['reportFilter'], $staff, null, $context['schoolFilter'])]
        ));
    }

    public function attendanceReports(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $context = $this->staffData->reportContext($request, $programIds, $staff);

        return view('staff.reports.attendance', array_merge(
            $this->layoutData('attendance-reports', 'Attendance Reports', 'Track and analyze attendance records and participation status.', 'Attendance Reports'),
            $context,
            ['report' => $this->staffData->attendanceReport($programIds, $context['reportFilter'], $staff, $context['schoolFilter'])]
        ));
    }

    public function participationReports(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $context = $this->staffData->reportContext($request, $programIds, $staff);

        return view('staff.reports.participation', array_merge(
            $this->layoutData('participation-reports', 'Participation Reports', 'Track and analyze scholar event participation and engagement.', 'Participation Reports'),
            $context,
            ['report' => $this->staffData->participationReport($programIds, $context['reportFilter'], $staff, $context['schoolFilter'])]
        ));
    }

    public function completionReports(Request $request)
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->scholar->syncCompletedEventHoursForPrograms($programIds);
        $context = $this->staffData->reportContext($request, $programIds, $staff);

        return view('staff.reports.completion', array_merge(
            $this->layoutData('completion-reports', 'Completion Reports', 'Track and analyze scholar completion and achievement status.', 'Completion Reports'),
            $context,
            ['report' => $this->staffData->completionReport($programIds, $context['reportFilter'], $staff, $context['schoolFilter'])]
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
        $scholar->activateFreshEventList();

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
            [
                'action' => 'approved',
                'scholar_id' => $scholar->id,
                'redirect' => route('staff.scholars.show', $scholar),
            ]
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

        $name = $scholar->full_name;
        $email = $scholar->email;
        $deletedId = (int) $scholar->id;
        $this->accounts->permanentlyDelete($scholar);
        app(\App\Services\WorkflowMailService::class)->sendToAddress(
            $email,
            $name,
            'Account Rejected',
            'Your scholar registration was not approved. You can register again with this email address.',
            \App\Support\OutboundAppUrl::withRoot(fn () => route('login')),
            'Go to sign in',
            'scholar-rejected:'.$deletedId
        );

        return $this->approvalActionSucceeded(
            $request,
            "{$name}'s application was rejected and permanently removed. Their email and scholar ID can be used to register again.",
            [
                'action' => 'rejected',
                'scholar_id' => $deletedId,
                'redirect' => route('staff.scholars'),
            ]
        );
    }

    public function deleteScholar(Request $request, User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canManageScholar($scholar), 403);

        if ($scholar->status === User::STATUS_PENDING) {
            return redirect()
                ->route('staff.scholars.show', $scholar)
                ->with('error', 'Pending applications must be approved or rejected. Use Reject to close a pending application.');
        }

        $name = $scholar->full_name;
        $this->accounts->permanentlyDelete($scholar);

        return redirect()
            ->route('staff.scholars')
            ->with(
                'success',
                "{$name}'s scholar account and all associated records were permanently deleted. Their email and scholar ID can be used to register again."
            );
    }

    public function blockScholar(User $scholar)
    {
        return $this->restrictScholar($scholar, User::STATUS_BLOCKED);
    }

    public function suspendScholar(Request $request, User $scholar)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->restrictScholar($scholar, User::STATUS_SUSPENDED, $validated['reason'] ?? null);
    }

    public function reactivateScholar(User $scholar)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canRestrictAccount($scholar), 403);

        if (! $scholar->isAccessRestricted()) {
            return back()->with('error', 'Only blocked or suspended scholar accounts can be reactivated.');
        }

        $this->accountAccess->reactivate($scholar, $staff);

        return back()->with('success', "{$scholar->full_name}'s account has been reactivated.");
    }

    private function restrictScholar(User $scholar, string $status, ?string $reason = null)
    {
        $staff = Auth::user();

        abort_unless($scholar->isScholar(), 404);
        abort_unless($staff->canRestrictAccount($scholar), 403);

        if (! $scholar->canBeRestricted()) {
            return back()->with('error', 'Only active scholar accounts can be blocked or suspended.');
        }

        if ($scholar->status === $status) {
            $label = $scholar->accountStatusLabel();

            return back()->with('error', "{$scholar->full_name}'s account is already {$label}.");
        }

        $this->accountAccess->restrict($scholar, $status, $staff, $reason);

        if ($status === User::STATUS_SUSPENDED) {
            $ends = $scholar->fresh()->openSuspension()?->ends_at;
            $until = $ends
                ? $ends->timezone(config('app.timezone'))->format('M j, Y g:i A')
                : '7 days from now';

            return back()->with('success', "{$scholar->full_name}'s account has been suspended for 7 days, until {$until}.");
        }

        return back()->with('success', "{$scholar->full_name}'s account has been blocked.");
    }

    private function approvalActionSucceeded(Request $request, string $message, array $extra = [])
    {
        $staff = Auth::user();
        $programIds = $this->staffData->programIds($staff);
        $this->staffData->forgetPendingApprovalsCache($programIds, $staff);
        $stats = $this->staffData->approvalRequestStats($programIds, $staff);

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => true,
                'message' => $message,
                'stats' => $stats,
                'pending_count' => $stats['pending'],
            ], $extra));
        }

        $redirect = $extra['redirect'] ?? null;

        if (is_string($redirect) && $redirect !== '') {
            return redirect()->to($redirect)->with('success', $message);
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

        if ($staff->isStaffRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your scholar staff account has been rejected and can no longer access the system. Please contact the system administrator for assistance.');
        }

        abort_unless($staff->isStaffPendingApproval(), 403);

        return view('staff.pending-approval', [
            'staff' => $staff,
            'showPendingStaffApprovalModal' => $request->session()->get('show_pending_staff_approval_modal', false),
        ]);
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

    public function updateEventSchedule(Request $request, Event $event)
    {
        $this->assertManagesEvent($event);

        if ($event->isAttendanceOpen()) {
            return back()->with('error', 'Close attendance before changing the event dates.');
        }

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'The event end date cannot be earlier than the start date.',
        ]);

        $event->update([
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
        ]);

        return back()->with('success', "{$event->title} is now scheduled for {$event->dateRangeLabel()}.");
    }

    public function openAttendance(Request $request, Event $event)
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
            $request->session()->flash('success', $message);

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
            Auth::user()->canViewStaffManagedEvent($event),
            403,
            'You can only manage attendance for events created after your scholar staff account became active for your assigned scholarship program.'
        );
    }

    /**
     * @param  list<array{key: string, label: string}>  $options
     * @return list<array{key: string, label: string}>
     */
    private function attendanceSchoolOptions(array $options): array
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

    private function attendanceEventPanel(array $programIds, int $selectedEventId, string $search = '', ?array $schoolFilter = null): array
    {
        $events = Event::query()
            ->with(['registrations.user', 'attendances.user'])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->visibleToStaff(Auth::user())
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
                $event->setAttribute('checked_in_count', $event->attendances->filter(
                    fn ($attendance) => $attendance->hasCheckedIn() && $attendance->status === Attendance::STATUS_APPROVED
                )->count());
                $event->setAttribute('pending_count', $event->attendances->filter(
                    fn ($attendance) => $attendance->hasCheckedIn() && $attendance->status === Attendance::STATUS_PENDING
                )->count());
                $attendanceFinalized = $event->attendanceMayBeFinalized();
                $event->setAttribute('failed_count', $event->registrations->filter(function ($registration) use ($event, $attendanceFinalized) {
                    $attendance = $event->attendances->firstWhere('user_id', $registration->user_id);

                    return ($attendanceFinalized && ! $attendance?->hasCheckedIn())
                        || $attendance?->status === Attendance::STATUS_FAILED_CHECK_IN
                        || $registration->status === EventRegistration::STATUS_FAILED_CHECK_IN;
                })->count());
            });

        $selected = $selectedEventId > 0
            ? $events->firstWhere('id', $selectedEventId)
            : null;

        $approved = collect();
        $pending = collect();
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

            $selected->load(['registrations.user.scholarshipClubSchool', 'attendances.user.scholarshipClubSchool']);
            $attendanceFinalized = $selected->attendanceMayBeFinalized();

            foreach ($selected->registrations as $registration) {
                $attendance = $selected->attendances->firstWhere('user_id', $registration->user_id);
                $row = [
                    'user' => $registration->user,
                    'attendance' => $attendance,
                ];

                if ($attendance?->hasCheckedIn()) {
                    if ($attendance->status === Attendance::STATUS_APPROVED) {
                        $approved->push($row);
                    } elseif ($attendance->status === Attendance::STATUS_PENDING) {
                        $pending->push($row);
                    }
                } elseif (
                    $attendanceFinalized
                    || $attendance?->status === Attendance::STATUS_FAILED_CHECK_IN
                    || $registration->status === EventRegistration::STATUS_FAILED_CHECK_IN
                ) {
                    $failed->push($row);
                }
            }

            $matchesSchool = fn (array $row) => $this->staffData->userMatchesSchoolFilter($row['user'] ?? null, $schoolFilter);
            $approved = $approved->filter($matchesSchool)->values();
            $pending = $pending->filter($matchesSchool)->values();
            $failed = $failed->filter($matchesSchool)->values();
        }

        return [
            'events' => $events,
            'selected' => $selected,
            'approved' => $approved,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }
}
