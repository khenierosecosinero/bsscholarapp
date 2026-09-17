<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AcademicSetting;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarNotification;
use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ScholarService
{
    public const REQUIRED_HOURS = 30;

    /** @var array<int, array<int, array<string, array<string, float>>>> */
    private array $hourAllocationCache = [];

    public function __construct(
        private AcademicSettingsService $academic,
        private ProgramScopeService $programScope,
    ) {}

    public function scopeEventsForUser(Builder $query, User $user): Builder
    {
        $query->whereIn('status', ['confirmed', 'upcoming', 'ongoing', 'completed', 'pending']);

        if (! $user->scholarship_program_id) {
            return $query->whereRaw('1 = 0');
        }

        return $this->programScope->scopeByPrograms($query, $user->visibleLocationIds());
    }

    public function scopeAnnouncementsForUser(Builder $query, User $user): Builder
    {
        if (! $user->scholarship_program_id) {
            return $query->whereRaw('1 = 0');
        }

        return $this->programScope->scopeByPrograms($query, $user->visibleLocationIds());
    }

    public function assertEventVisibleToUser(Event $event, User $user): void
    {
        $this->programScope->assertEventVisibleToScholar($event, $user);
    }

    public function assertAnnouncementVisibleToUser(Announcement $announcement, User $user): void
    {
        $this->programScope->assertAnnouncementVisibleToScholar($announcement, $user);
    }

    public function semesterStart(User $user): Carbon
    {
        [$start] = $this->academic->semesterBounds($this->academic->forUser($user));

        return $start;
    }

    public function semesterEnd(User $user): Carbon
    {
        [, $end] = $this->academic->semesterBounds($this->academic->forUser($user));

        return $end;
    }

    public function currentSemesterInfo(User $user, ?array $hours = null): array
    {
        $hours = $hours ?? $this->serviceHourStats($user);

        return $this->academic->infoForUser($user, $hours);
    }

    /**
     * Semester progress after applying the per-semester requirement cap and
     * carrying leftover approved hours into later semesters.
     *
     * Attendance rows are left unchanged; only the credited totals move.
     */
    public function serviceHourStats(User $user, ?AcademicSetting $period = null): array
    {
        $period ??= $this->academic->forUser($user);

        return $this->statsFromSlot(
            $this->slotFromAllocation($this->allocatedHours($user), $period)
        );
    }

    public function serviceHourStatsFromRecords(Collection $records, AcademicSetting $period): array
    {
        return $this->statsFromSlot(
            $this->slotFromAllocation($this->allocateHoursFromRecords($records), $period)
        );
    }

    public function allocatedHours(User $user): array
    {
        $cacheKey = (int) $user->id;
        if (! isset($this->hourAllocationCache[$cacheKey])) {
            $records = $user->attendances()
                ->whereNotNull('check_in')
                ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
                ->get(['academic_year_start', 'semester', 'status', 'hours_earned']);

            $this->hourAllocationCache[$cacheKey] = $this->allocateHoursFromRecords($records);
        }

        return $this->hourAllocationCache[$cacheKey];
    }

    /**
     * Walk academic years in order and credit at most REQUIRED_HOURS per semester.
     * Surplus approved hours become carried_in for the following semester.
     *
     * @param  iterable<int, object>  $records
     * @return array<int, array<string, array<string, float>>>
     */
    public function allocateHoursFromRecords(iterable $records, ?float $required = null): array
    {
        $required = $required ?? (float) self::REQUIRED_HOURS;
        $buckets = [];

        foreach ($records as $row) {
            $year = (int) ($row->academic_year_start ?? 0);
            $semester = (string) ($row->semester ?? '');
            if ($year < 1 || ! in_array($semester, AcademicSettingsService::SEMESTERS, true)) {
                continue;
            }

            $buckets[$year][$semester] ??= ['approved' => 0.0, 'pending' => 0.0];
            $hours = (float) ($row->hours_earned ?? 0);

            if ($row->status === Attendance::STATUS_APPROVED) {
                $buckets[$year][$semester]['approved'] += $hours;
            } elseif ($row->status === Attendance::STATUS_PENDING) {
                $buckets[$year][$semester]['pending'] += $hours;
            }
        }

        if ($buckets === []) {
            return [];
        }

        ksort($buckets);
        $minYear = (int) min(array_keys($buckets));
        $maxYear = (int) max(array_keys($buckets));
        $carry = 0.0;
        $allocated = [];

        for ($year = $minYear; $year <= $maxYear || $carry > 0; $year++) {
            if ($year - $minYear > 40) {
                break;
            }

            foreach (AcademicSettingsService::SEMESTERS as $semester) {
                $raw = round((float) ($buckets[$year][$semester]['approved'] ?? 0), 2);
                $pending = round((float) ($buckets[$year][$semester]['pending'] ?? 0), 2);
                $total = round($raw + $carry, 2);
                $credited = round(min($total, $required), 2);
                $carriedOut = round(max(0, $total - $required), 2);

                $allocated[$year][$semester] = [
                    'raw_approved' => $raw,
                    'pending' => $pending,
                    'carried_in' => round($carry, 2),
                    'credited' => $credited,
                    'carried_out' => $carriedOut,
                ];

                $carry = $carriedOut;
            }
        }

        return $allocated;
    }

    public function semesterSummaryChart(User $user): array
    {
        $stats = $this->serviceHourStats($user);
        $required = max(1, $stats['required']);

        $approvedArc = min(100, (int) round(($stats['approved'] / $required) * 100));
        $pendingArc = min(100 - $approvedArc, (int) round(($stats['pending'] / $required) * 100));
        $remainingArc = max(0, 100 - $approvedArc - $pendingArc);

        return [
            'approved' => $stats['approved'],
            'pending' => $stats['pending'],
            'remaining' => $stats['remaining'],
            'required' => $stats['required'],
            'completed_pct' => min(100, (int) round(($stats['approved'] / $required) * 100)),
            'pending_pct' => min(100, (int) round(($stats['pending'] / $required) * 100)),
            'remaining_pct' => min(100, (int) round(($stats['remaining'] / $required) * 100)),
            'approved_arc' => $approvedArc,
            'pending_arc' => $pendingArc,
            'remaining_arc' => $remainingArc,
            'pending_offset' => $approvedArc,
            'remaining_offset' => $approvedArc + $pendingArc,
        ];
    }

    public function semesterHoursComparison(User $user): array
    {
        $period = $this->academic->forUser($user);
        $required = (float) self::REQUIRED_HOURS;
        $maxScale = 40;
        $allocation = $this->allocatedHours($user);

        $semesters = collect(AcademicSettingsService::SEMESTERS)->map(function (string $semester) use ($period, $required, $maxScale, $allocation) {
            $slot = $allocation[$period->year_start][$semester] ?? [
                'credited' => 0.0,
            ];
            $approved = (float) ($slot['credited'] ?? 0);

            return [
                'label' => $semester,
                'short_label' => str_replace(' Semester', '', $semester),
                'approved' => $approved,
                'required' => $required,
                'bar_height' => min(100, (int) round(($approved / $maxScale) * 100)),
                'required_line' => (int) round(($required / $maxScale) * 100),
            ];
        })->all();

        return [
            'required' => $required,
            'max_scale' => $maxScale,
            'semesters' => $semesters,
            'required_line' => (int) round(($required / $maxScale) * 100),
        ];
    }

    /**
     * @param  array<int, array<string, array<string, float>>>  $allocation
     * @return array<string, float>
     */
    private function slotFromAllocation(array $allocation, AcademicSetting $period): array
    {
        return $allocation[$period->year_start][$period->semester] ?? [
            'raw_approved' => 0.0,
            'pending' => 0.0,
            'carried_in' => 0.0,
            'credited' => 0.0,
            'carried_out' => 0.0,
        ];
    }

    /**
     * @param  array<string, float>  $slot
     * @return array<string, float|int>
     */
    private function statsFromSlot(array $slot): array
    {
        $required = (float) self::REQUIRED_HOURS;
        $approved = round(min($required, max(0, (float) ($slot['credited'] ?? 0))), 2);
        $pending = round(max(0, (float) ($slot['pending'] ?? 0)), 2);

        return [
            'approved' => $approved,
            'pending' => $pending,
            'required' => $required,
            'remaining' => max(0, round($required - $approved, 2)),
            'raw_approved' => (float) ($slot['raw_approved'] ?? 0),
            'carried_in' => (float) ($slot['carried_in'] ?? 0),
            'carried_out' => (float) ($slot['carried_out'] ?? 0),
        ];
    }

    public function dashboardStats(User $user, ?array $hours = null, ?int $unreadNotifications = null): array
    {
        $hours = $hours ?? $this->serviceHourStats($user);

        return [
            'service_hours' => [
                'value' => $hours['approved'] . ' / ' . $hours['required'],
                'completed' => $hours['approved'],
                'required' => $hours['required'],
            ],
            'upcoming_events' => $this->scopeEventsForUser(
                Event::query()->where('starts_at', '>=', now())
                    ->where('starts_at', '<=', now()->addWeek()),
                $user
            )->count(),
            'pending_attendances' => $this->academic
                ->scopeAttendancesForPeriod(
                    $user->attendances()->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in'),
                    $this->academic->forUser($user)
                )
                ->count(),
            'notifications' => $unreadNotifications ?? $user->unreadNotificationCount(),
        ];
    }

    public function upcomingEvents(User $user, int $limit = 10): Collection
    {
        $attendances = $this->attendancesByEvent($user);

        return $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->limit($limit),
            $user
        )->get()
            ->map(fn (Event $event) => $this->formatEvent($event, $user, $attendances));
    }

    public function nextUpcomingEvent(User $user): ?array
    {
        $attendances = $this->attendancesByEvent($user);

        $event = $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->where('starts_at', '>', now())
                ->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id))
                ->orderBy('starts_at'),
            $user
        )->first();

        if (! $event) {
            $event = $this->scopeEventsForUser(
                Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                    ->where('starts_at', '>', now())
                    ->orderBy('starts_at'),
                $user
            )->first();
        }

        return $event ? $this->formatEvent($event, $user, $attendances) : null;
    }

    public function eventsForUser(User $user, ?string $search = null, ?string $status = null): Collection
    {
        $query = $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->orderBy('starts_at'),
            $user
        );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $events = $query->get();
        $attendances = $this->attendancesByEvent($user);
        $formatted = $events->map(fn (Event $event) => $this->formatEvent($event, $user, $attendances));

        if ($status && $status !== 'all') {
            $formatted = $formatted->filter(function (array $event) use ($status) {
                if ($status === 'upcoming') {
                    return ! $event['is_past'];
                }

                return $event['calendar_status'] === $status;
            })->values();
        }

        return $formatted;
    }

    public function formatEvent(Event $event, User $user, ?Collection $attendances = null): array
    {
        $registration = $event->registrations->first()
            ?? $user->eventRegistrations()->where('event_id', $event->id)->first();
        $attendance = $attendances
            ? $attendances->get($event->id)
            : $user->attendances()->where('event_id', $event->id)->first();

        $attendance = $this->applyMissedCheckIn($event, $user, $registration, $attendance);
        if ($attendance && ! $attendance->relationLoaded('event')) {
            $attendance->setRelation('event', $event);
        }
        $hasParticipated = $this->hasParticipated($attendance);
        $calendarStatus = $this->calendarStatus($event, $user, $attendance, $registration);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'date' => $event->starts_at->format('M d'),
            'month' => strtoupper($event->starts_at->format('M')),
            'day' => $event->starts_at->format('d'),
            'dow' => strtoupper($event->starts_at->format('D')),
            'time' => $event->starts_at->format('g:i A') . ' - ' . $event->ends_at->format('g:i A'),
            'full_date' => $event->starts_at->format('F d, Y') . ' (' . $event->starts_at->format('l') . ')',
            'hours' => $event->service_hours,
            'organizer' => $event->organizer,
            'image_url' => $event->image_url,
            'registration_status' => $registration?->status ?? 'not_joined',
            'has_participated' => $hasParticipated,
            'calendar_status' => $calendarStatus,
            'status_label' => $this->eventStatusLabel($calendarStatus),
            'status_class' => $this->eventStatusClass($calendarStatus),
            'failed_to_check_in' => $calendarStatus === 'failed_to_check_in',
            'calendar_days' => $event->calendarDateKeys(),
            'attendance' => $attendance,
            'attendance_open' => $event->isAttendanceOpen(),
            'attendance_status' => $event->attendanceStatusLabel(),
            'attendance_opened_at' => $event->attendanceOpenedAtLabel(),
            'attendance_closed_at' => $event->attendanceClosedAtLabel(),
            'attendance_message' => $this->attendanceSessionMessage($event),
            'can_check_in' => $event->isAttendanceOpen()
                && $registration
                && in_array($registration->status, [
                    EventRegistration::STATUS_CONFIRMED,
                    EventRegistration::STATUS_FAILED_CHECK_IN,
                ], true)
                && ! $attendance?->hasCheckedIn(),
            'can_check_out' => $attendance?->scholarCanCheckOut() ?? false,
            'can_modify_attendance' => $attendance?->scholarCanModify() ?? false,
            'is_past' => $event->hasEnded(),
        ];
    }

    public function hasParticipated(?Attendance $attendance): bool
    {
        return $attendance !== null
            && $attendance->status === Attendance::STATUS_APPROVED
            && $attendance->hasCheckedIn();
    }

    public function attendanceSessionMessage(Event $event): string
    {
        $eventStamp = sprintf(
            '%s on %s at %s',
            $event->title,
            $event->starts_at?->format('M j, Y') ?? 'the scheduled date',
            $event->starts_at?->format('g:i A') ?? 'the scheduled time'
        );

        if ($event->isAttendanceOpen()) {
            $opened = $event->attendanceOpenedAtLabel() ?? now()->format('M j, Y g:i A');

            return "Attendance for {$eventStamp} is OPEN as of {$opened}. You may mark your attendance until Scholar Staff closes the session.";
        }

        if ($event->attendanceSessionClosed()) {
            $closed = $event->attendanceClosedAtLabel() ?? now()->format('M j, Y g:i A');

            return "Attendance for {$eventStamp} is CLOSED as of {$closed}. You can no longer submit or modify your attendance.";
        }

        return "Attendance for {$eventStamp} is CLOSED. You can mark attendance only after Scholar Staff opens the session.";
    }

    public function missedCheckIn(Event $event, ?EventRegistration $registration, ?Attendance $attendance): bool
    {
        if ($event->isAttendanceOpen()) {
            return false;
        }

        if (! $event->attendanceSessionClosed() && ! $event->hasEnded()) {
            return false;
        }

        $registered = $registration && in_array($registration->status, [
            EventRegistration::STATUS_CONFIRMED,
            EventRegistration::STATUS_FAILED_CHECK_IN,
        ], true);

        if (! $registered) {
            return false;
        }

        return ! $attendance?->hasCheckedIn();
    }

    public function applyMissedCheckIn(Event $event, User $user, ?EventRegistration $registration, ?Attendance $attendance): ?Attendance
    {
        if (! $this->missedCheckIn($event, $registration, $attendance)) {
            return $attendance;
        }

        $alreadyMarked = $registration?->status === EventRegistration::STATUS_FAILED_CHECK_IN
            && $attendance
            && $attendance->status === Attendance::STATUS_FAILED_CHECK_IN
            && (float) ($attendance->hours_earned ?? 0) <= 0;

        if ($alreadyMarked) {
            return $attendance;
        }

        if ($registration && $registration->status !== EventRegistration::STATUS_FAILED_CHECK_IN) {
            $registration->update(['status' => EventRegistration::STATUS_FAILED_CHECK_IN]);
        }

        $stamp = $this->academic->attendanceStamp($this->academic->forUser($user));

        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'event_id' => $event->id],
            array_merge($stamp, [
                'status' => Attendance::STATUS_FAILED_CHECK_IN,
                'hours_earned' => 0,
                'remarks' => $event->attendanceSessionClosed()
                    ? 'Did not check in before attendance was closed. No service hours credited.'
                    : 'Did not check in during the event. No service hours credited.',
            ])
        );

        $this->logActivity($user, 'attendance', "Failed to check in for {$event->title}. No service hours credited.");
        $this->notify(
            $user,
            'Failed to Check In',
            "You registered for {$event->title} but did not check in through the attendance system. No service hours were credited.",
            'attendance',
            true
        );

        return $attendance;
    }

    public function syncMissedCheckInsForUser(User $user): void
    {
        if (! $this->shouldRunSync('scholar.sync_missed.user.'.$user->id)) {
            return;
        }

        $registrations = EventRegistration::query()
            ->with('event')
            ->where('user_id', $user->id)
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_FAILED_CHECK_IN])
            ->whereHas('event', fn ($q) => $q->where(function ($event) {
                $event->where('ends_at', '<', now())
                    ->orWhere(function ($closed) {
                        $closed->where('attendance_is_open', false)
                            ->whereNotNull('attendance_opened_at')
                            ->whereNotNull('attendance_closed_at');
                    });
            }))
            ->get();

        $attendances = $this->attendancesByEvent($user);

        foreach ($registrations as $registration) {
            if ($registration->event) {
                $this->applyMissedCheckIn(
                    $registration->event,
                    $user,
                    $registration,
                    $attendances->get($registration->event_id)
                );
            }
        }
    }

    public function syncMissedCheckInsForPrograms(array $programIds): void
    {
        if (! $this->shouldRunSync('scholar.sync_missed.programs.'.$this->programCacheKey($programIds))) {
            return;
        }

        $registrations = EventRegistration::query()
            ->with(['event', 'user'])
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_FAILED_CHECK_IN])
            ->whereHas('event', fn ($q) => $q->where(function ($event) {
                $event->where('ends_at', '<', now())
                    ->orWhere(function ($closed) {
                        $closed->where('attendance_is_open', false)
                            ->whereNotNull('attendance_opened_at')
                            ->whereNotNull('attendance_closed_at');
                    });
            }))
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->get();

        foreach ($registrations as $registration) {
            if (! $registration->event || ! $registration->user) {
                continue;
            }

            $attendance = Attendance::query()
                ->where('user_id', $registration->user_id)
                ->where('event_id', $registration->event_id)
                ->first();

            $this->applyMissedCheckIn(
                $registration->event,
                $registration->user,
                $registration,
                $attendance
            );
        }
    }

    public function syncMissedCheckInsForEvent(Event $event): void
    {
        $registrations = EventRegistration::query()
            ->with('user')
            ->where('event_id', $event->id)
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_FAILED_CHECK_IN])
            ->get();

        if ($registrations->isEmpty()) {
            return;
        }

        $attendances = Attendance::query()
            ->where('event_id', $event->id)
            ->whereIn('user_id', $registrations->pluck('user_id')->all())
            ->get()
            ->keyBy('user_id');

        foreach ($registrations as $registration) {
            if (! $registration->user) {
                continue;
            }

            $this->applyMissedCheckIn(
                $event,
                $registration->user,
                $registration,
                $attendances->get($registration->user_id)
            );
        }
    }

    public function restoreCheckInEligibility(Event $event): void
    {
        $registrations = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', EventRegistration::STATUS_FAILED_CHECK_IN)
            ->get();

        foreach ($registrations as $registration) {
            $attendance = Attendance::query()
                ->where('user_id', $registration->user_id)
                ->where('event_id', $event->id)
                ->first();

            if ($attendance?->hasCheckedIn()) {
                continue;
            }

            $registration->update(['status' => EventRegistration::STATUS_CONFIRMED]);

            if ($attendance && $attendance->status === Attendance::STATUS_FAILED_CHECK_IN) {
                $attendance->update([
                    'status' => Attendance::STATUS_PENDING,
                    'remarks' => null,
                ]);
            }
        }
    }

    public function calendarStatus(Event $event, User $user, ?Attendance $attendance = null, ?EventRegistration $registration = null): string
    {
        $registration ??= $event->registrations->first()
            ?? $user->eventRegistrations()->where('event_id', $event->id)->first();

        if ($this->hasParticipated($attendance)) {
            return 'participated';
        }

        if ($this->missedCheckIn($event, $registration, $attendance)) {
            return 'failed_to_check_in';
        }

        if ($attendance?->status === Attendance::STATUS_PENDING && $attendance->check_out && $attendance->hasCheckedIn()) {
            return 'pending';
        }

        return $this->registrationStatus($event, $user);
    }

    public function eventStatusLabel(string $calendarStatus): string
    {
        return match ($calendarStatus) {
            'participated' => 'Participated',
            'confirmed' => 'Confirmed',
            'pending' => 'Pending',
            'failed_to_check_in' => 'Failed to Check In',
            default => 'Not Joined',
        };
    }

    public function eventStatusClass(string $calendarStatus): string
    {
        return match ($calendarStatus) {
            'participated' => 'participated',
            'confirmed' => 'confirmed',
            'pending' => 'pending',
            'failed_to_check_in' => 'failed-to-check-in',
            default => 'not-joined',
        };
    }

    public function scheduleSummaryStats(Collection $events): array
    {
        return [
            'confirmed' => $events->where('calendar_status', 'confirmed')->count(),
            'participated' => $events->where('calendar_status', 'participated')->count(),
            'pending' => $events->where('calendar_status', 'pending')->count(),
            'notJoined' => $events->where('calendar_status', 'not_joined')->count(),
            'failedCheckIn' => $events->where('calendar_status', 'failed_to_check_in')->count(),
        ];
    }

    public function confirmParticipation(Attendance $attendance): void
    {
        if ($attendance->status === Attendance::STATUS_APPROVED) {
            return;
        }

        if (! $attendance->check_in || ! $attendance->check_out) {
            throw new \InvalidArgumentException('Attendance must include check-in and check-out before participation can be confirmed.');
        }

        if (! $attendance->hasPhoto()) {
            throw new \InvalidArgumentException('A participation photo is required before service hours can be approved.');
        }

        if ($attendance->status === Attendance::STATUS_FAILED_CHECK_IN) {
            throw new \InvalidArgumentException('This scholar failed to check in. No service hours can be credited.');
        }

        $hours = $this->hoursForCompletedEvent($attendance);

        $attendance->update([
            'status' => Attendance::STATUS_APPROVED,
            'hours_earned' => $hours,
            'remarks' => null,
        ]);

        $user = $attendance->user;
        $event = $attendance->event;

        $this->logActivity($user, 'attendance', "Participation confirmed for {$event->title}");
        $this->notify(
            $user,
            'Participation Confirmed',
            "Your participation in {$event->title} has been officially confirmed.",
            'attendance'
        );

        if ($hours) {
            $this->notify(
                $user,
                'Service Hours Updated',
                "{$hours} service hours have been credited for {$event->title}.",
                'service_hours'
            );
        }
    }

    public function storeAttendancePhoto(Attendance $attendance, UploadedFile $file): void
    {
        if (! $attendance->canReplacePhoto()) {
            throw new \InvalidArgumentException('This attendance record can no longer accept a new photo.');
        }

        $path = $file->store("attendance_photos/{$attendance->user_id}", 'public');
        $oldPath = $attendance->photo_path;
        $wasRejected = $attendance->status === Attendance::STATUS_REJECTED;
        $hours = $attendance->check_out
            ? $this->eventHourValue($attendance->event)
            : $attendance->hours_earned;

        $attendance->update([
            'photo_path' => $path,
            'photo_original_name' => $file->getClientOriginalName(),
            'photo_uploaded_at' => now(),
            'status' => $wasRejected ? Attendance::STATUS_PENDING : $attendance->status,
            'remarks' => $wasRejected ? null : $attendance->remarks,
            'hours_earned' => $hours,
        ]);

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $user = $attendance->user;
        $event = $attendance->event;
        $this->logActivity($user, 'attendance', "Uploaded a participation photo for {$event->title}");
        $this->notify(
            $user,
            'Participation Photo Submitted',
            "Your photo for {$event->title} was submitted. Scholar Staff will review it before approving service hours.",
            'attendance'
        );
    }

    public function rejectParticipation(Attendance $attendance, ?string $remarks = null): void
    {
        if ($attendance->status === Attendance::STATUS_FAILED_CHECK_IN) {
            throw new \InvalidArgumentException('This scholar failed to check in. The record cannot be reviewed.');
        }

        if ($attendance->status === Attendance::STATUS_APPROVED) {
            throw new \InvalidArgumentException('Approved attendance cannot be rejected.');
        }

        $attendance->update([
            'status' => Attendance::STATUS_REJECTED,
            'hours_earned' => 0,
            'remarks' => $remarks ?: 'Attendance was not verified. No service hours credited.',
        ]);

        $user = $attendance->user;
        $event = $attendance->event;
        $reason = $remarks ? " Reason: {$remarks}" : '';

        $this->logActivity($user, 'attendance', "Participation rejected for {$event->title}");
        $this->notify(
            $user,
            'Attendance Not Verified',
            "Your attendance for {$event->title} was not approved.{$reason} You may upload a clearer participation photo and wait for Scholar Staff to review it again.",
            'attendance',
            true
        );
    }

    public function eventHourValue(?Event $event): float
    {
        return round((float) ($event?->service_hours ?? 0), 2);
    }

    public function hoursForCompletedEvent(Attendance $attendance, ?Event $event = null): float
    {
        $event ??= $attendance->event ?? $attendance->loadMissing('event')->event;

        if (
            ! $event
            || ! $attendance->hasCheckedIn()
            || $attendance->status === Attendance::STATUS_FAILED_CHECK_IN
            || $attendance->status === Attendance::STATUS_REJECTED
        ) {
            return 0.0;
        }

        return $this->eventHourValue($event);
    }

    public function syncCompletedEventHoursForUser(User $user): void
    {
        if (! $this->shouldRunSync('scholar.sync_hours.user.'.$user->id)) {
            return;
        }

        $user->attendances()
            ->with('event')
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->whereIn('status', [Attendance::STATUS_PENDING, Attendance::STATUS_APPROVED])
            ->get()
            ->each(function (Attendance $attendance) {
                $hours = $this->hoursForCompletedEvent($attendance);
                if ((float) $attendance->hours_earned !== $hours) {
                    $attendance->update(['hours_earned' => $hours]);
                }
            });
    }

    public function syncCompletedEventHoursForPrograms(array $programIds): void
    {
        if (! $this->shouldRunSync('scholar.sync_hours.programs.'.$this->programCacheKey($programIds))) {
            return;
        }

        Attendance::query()
            ->with('event')
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->whereIn('status', [Attendance::STATUS_PENDING, Attendance::STATUS_APPROVED])
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->get()
            ->each(function (Attendance $attendance) {
                $hours = $this->hoursForCompletedEvent($attendance);
                if ((float) $attendance->hours_earned !== $hours) {
                    $attendance->update(['hours_earned' => $hours]);
                }
            });
    }

    public function registrationStatus(Event $event, User $user): string
    {
        $registration = $event->registrations->first()
            ?? $user->eventRegistrations()->where('event_id', $event->id)->first();

        return $registration?->status ?? 'not_joined';
    }

    public function calendarEvents(User $user, int $year, int $month): Collection
    {
        $monthDate = Carbon::create($year, $month, 1);
        $start = $monthDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end = $monthDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->endOfDay();
        $attendances = $this->attendancesByEvent($user);

        return $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->overlappingDates($start, $end)
                ->orderBy('starts_at'),
            $user
        )->get()
            ->map(fn (Event $event) => $this->formatEvent($event, $user, $attendances));
    }

    public function ensureUserDocuments(User $user): void
    {
        if (! $user->scholarship_program_id) {
            return;
        }

        $cacheKey = 'scholar.docs_ensured.'.$user->id.'.'.$user->scholarship_program_id;
        if (Cache::get($cacheKey)) {
            return;
        }

        $types = DocumentType::query()
            ->where('scholarship_program_id', $user->scholarship_program_id)
            ->get();

        $existingTypeIds = Document::query()
            ->where('user_id', $user->id)
            ->pluck('document_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($types as $type) {
            if (in_array($type->id, $existingTypeIds, true)) {
                continue;
            }

            Document::firstOrCreate(
                ['user_id' => $user->id, 'document_type_id' => $type->id],
                ['status' => 'not_submitted']
            );
        }

        Cache::put($cacheKey, true, 300);
    }

    public function documentsForUser(User $user): Collection
    {
        $this->ensureUserDocuments($user);

        return Document::query()
            ->with('documentType')
            ->where('user_id', $user->id)
            ->get()
            ->sortBy(fn (Document $document) => $document->documentType?->name ?? '')
            ->values();
    }

    public function documentOverviewStats(User $user, ?Collection $documents = null): array
    {
        $documents = $documents ?? $this->documentsForUser($user);
        $total = $documents->count();

        $approved = $documents->where('status', 'approved')->count();
        $pending = $documents->whereIn('status', ['pending', 'submitted'])->count();
        $rejected = $documents->where('status', 'rejected')->count();
        $notSubmitted = $documents->filter(fn (Document $document) => ! $document->isSubmitted())->count();
        $submitted = $documents->filter(fn (Document $document) => $document->hasFile())->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'not_submitted' => $notSubmitted,
            'submitted' => $submitted,
            'completion_pct' => $total > 0 ? (int) round(($approved / $total) * 100) : 0,
            'submitted_pct' => $total > 0 ? (int) round(($submitted / $total) * 100) : 0,
        ];
    }

    public function provisionDocumentType(DocumentType $type): int
    {
        if (! $type->scholarship_program_id) {
            return 0;
        }

        $count = 0;

        User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('scholarship_program_id', $type->scholarship_program_id)
            ->chunkById(100, function ($scholars) use ($type, &$count) {
                foreach ($scholars as $scholar) {
                    Document::firstOrCreate(
                        ['user_id' => $scholar->id, 'document_type_id' => $type->id],
                        ['status' => 'not_submitted']
                    );
                    $count++;
                    Cache::forget('scholar.docs_ensured.'.$scholar->id.'.'.$type->scholarship_program_id);
                }
            });

        return $count;
    }

    public function notifyScholarsOfDocumentType(DocumentType $type, array $programIds = []): int
    {
        if (! $type->scholarship_program_id) {
            return 0;
        }

        $scholarsQuery = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $type->scholarship_program_id);

        $title = 'Required document: '.$type->name;
        $body = $type->description
            ? "Scholar Staff posted a required document: {$type->name}. {$type->description} Please submit it on your Documents page."
            : "Scholar Staff posted a required document: {$type->name}. Please submit it on your Documents page.";

        $count = 0;

        $scholarsQuery->each(function (User $scholar) use ($title, $body, &$count) {
            ScholarNotification::updateOrCreate(
                [
                    'user_id' => $scholar->id,
                    'title' => $title,
                ],
                [
                    'body' => $body,
                    'category' => 'documents',
                    'is_important' => true,
                    'is_read' => false,
                ]
            );
            $count++;
        });

        return $count;
    }

    public function notifyDocumentReview(Document $document, string $status, ?string $notes = null): void
    {
        $document->loadMissing(['user', 'documentType']);
        $scholar = $document->user;
        $typeName = $document->documentType?->name ?? 'document';

        if (! $scholar) {
            return;
        }

        [$title, $body] = match ($status) {
            'approved' => [
                'Document Approved',
                "Your {$typeName} has been approved.",
            ],
            'rejected' => [
                'Document Rejected',
                "Your {$typeName} was rejected.".($notes ? " Reason: {$notes}" : ' Please upload a clearer copy on your Documents page.'),
            ],
            default => [
                'Document Pending Review',
                "Your {$typeName} is pending review by Scholar Staff.",
            ],
        };

        $this->notify($scholar, $title, $body, 'documents', $status === 'rejected');
        $this->logActivity($scholar, 'document', "Document \"{$typeName}\" marked as {$status}");
    }

    public function logActivity(User $user, string $type, string $description, array $metadata = []): void
    {
        UserActivity::create([
            'user_id' => $user->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function notify(User $user, string $title, string $body, string $category = 'system', bool $important = false, ?int $eventId = null): void
    {
        ScholarNotification::create([
            'user_id' => $user->id,
            'event_id' => $eventId,
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'is_important' => $important,
        ]);
    }

    public function notifyScholarsOfPublishedEvent(Event $event): int
    {
        if (! $event->scholarship_program_id) {
            return 0;
        }

        $scholarsQuery = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $event->scholarship_program_id);

        $title = 'New Event: '.$event->title;
        $body = sprintf(
            '%s has been scheduled on %s at %s (%s). Service hours: %s. Visit the Events page to view details and register.',
            $event->title,
            $event->starts_at->format('M j, Y'),
            $event->starts_at->format('g:i A'),
            $event->location,
            number_format((float) $event->service_hours, 1).' hrs'
        );

        $count = 0;

        $scholarsQuery->each(function (User $scholar) use ($event, $title, $body, &$count) {
            ScholarNotification::updateOrCreate(
                [
                    'user_id' => $scholar->id,
                    'title' => $title,
                ],
                [
                    'event_id' => $event->id,
                    'body' => $body,
                    'category' => 'event_reminder',
                    'is_important' => true,
                    'is_read' => false,
                ]
            );
            $count++;
        });

        return $count;
    }

    private function attendancesByEvent(User $user): Collection
    {
        return $user->attendances()->get()->keyBy('event_id');
    }

    private function shouldRunSync(string $key, int $seconds = 45): bool
    {
        return Cache::add($key, 1, $seconds);
    }

    private function programCacheKey(array $programIds): string
    {
        $programIds = array_values(array_unique(array_map('intval', $programIds)));
        sort($programIds);

        return md5(implode(',', $programIds));
    }
}
