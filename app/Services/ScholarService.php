<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScholarService
{
    public const REQUIRED_HOURS = 30;

    /** @var array<int, array<int, array<string, array<string, float>>>> */
    private array $hourAllocationCache = [];

    public function __construct(
        private AcademicSettingsService $academic,
        private ProgramScopeService $programScope,
        private AttendanceDriveStorageService $attendanceDrive,
    ) {}

    public function scopeEventsForUser(Builder $query, User $user): Builder
    {
        $query->whereIn('status', [
            Event::STATUS_PENDING,
            Event::STATUS_CONFIRMED,
            Event::STATUS_ONGOING,
            Event::STATUS_COMPLETED,
            Event::STATUS_CANCELLED,
            'upcoming', // legacy values retained for older rows
        ]);

        if (! $user->scholarship_program_id) {
            return $query->whereRaw('1 = 0');
        }

        $this->programScope->scopeByPrograms($query, $user->visibleLocationIds());

        return $query->visibleToAccount($user);
    }

    public function scopeEventsForSelectedPeriod(Builder $query): Builder
    {
        [$start, $end] = $this->academic->eventBoundsForReport($this->academic->selectedReportFilter());

        return $query->overlappingDates($start, $end);
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
        $period ??= $this->academic->selectedPeriod();

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
        $year = (int) $this->academic->selectedPeriod()->year_start;
        $cacheKey = (int) $user->id.':'.$year;
        if (! isset($this->hourAllocationCache[$cacheKey])) {
            $records = $user->attendances()
                ->whereNotNull('check_in')
                ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
                ->where('academic_year_start', $year)
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
        $period = $this->academic->selectedPeriod();
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
            'remaining' => (float) max(0, round($required - $approved, 2)),
            'raw_approved' => (float) ($slot['raw_approved'] ?? 0),
            'carried_in' => (float) ($slot['carried_in'] ?? 0),
            'carried_out' => (float) ($slot['carried_out'] ?? 0),
        ];
    }

    /**
     * Year 1 of this scholar's 4-year program (the original scholarship start).
     * Null when the scholar has not saved a Start Academic Year yet.
     */
    public function startingAcademicYear(User $user): ?int
    {
        if (! $user->isScholar()) {
            return null;
        }

        return $user->program_start_year ? (int) $user->program_start_year : null;
    }

    /**
     * Academic years a scholar may choose as Start Academic Year.
     * Includes the scholar's saved start year even if it was later removed
     * from the administrator's configured list.
     *
     * @return array<int, string>
     */
    public function programStartYearOptions(?User $user = null): array
    {
        $options = $this->academic->yearOptions();

        if ($user?->program_start_year) {
            $year = (int) $user->program_start_year;
            if (! isset($options[$year])) {
                $options[$year] = $year.'–'.($year + 1);
                ksort($options);
            }
        }

        return $options;
    }

    /**
     * Permanently save Year 1. Viewing filters and the administrator's current
     * academic year do not change this value.
     */
    public function setProgramStartYear(User $user, int $yearStart): int
    {
        if (! $user->isScholar()) {
            throw ValidationException::withMessages([
                'program_start_year' => 'Only scholars have a Start Academic Year.',
            ]);
        }

        $options = $this->academic->yearOptions();
        if (! isset($options[$yearStart])) {
            throw ValidationException::withMessages([
                'program_start_year' => 'Choose a Start Academic Year configured by the Administrator.',
            ]);
        }

        if ((int) $user->program_start_year !== $yearStart) {
            $user->forceFill(['program_start_year' => $yearStart])->save();
        }

        return $yearStart;
    }

    /**
     * Keep an existing Start Academic Year. Does not invent a year when missing.
     */
    public function syncProgramStartYear(User $user, bool $recalculate = false): ?int
    {
        return $this->startingAcademicYear($user);
    }

    /**
     * Year 1 through Year 4 for this scholar. The selected academic year only
     * highlights a semester. Attendance rows are not moved.
     *
     * @return array<string, mixed>
     */
    public function programHourTracking(User $user): array
    {
        $required = (float) self::REQUIRED_HOURS;
        $period = $this->academic->selectedPeriod();
        $startYear = $this->startingAcademicYear($user);
        $hourStats = $this->serviceHourStats($user, $period);

        if ($startYear === null) {
            return [
                'required' => $required,
                'start_year' => null,
                'end_year' => null,
                'needs_start_year' => true,
                'year_start' => (int) $period->year_start,
                'year_end' => (int) $period->year_start + 1,
                'academic_year' => 'AY '.$period->year_start.'–'.((int) $period->year_start + 1),
                'years' => [],
                'current' => $hourStats,
                'period' => $this->academic->infoForUser($user, $hourStats),
            ];
        }

        $endYear = $startYear + 3;
        $allocation = $this->allocateHoursFromRecords(
            $user->attendances()
                ->whereNotNull('check_in')
                ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
                ->whereBetween('academic_year_start', [$startYear, $endYear])
                ->get(['academic_year_start', 'semester', 'status', 'hours_earned'])
        );

        $years = [];
        $programYear = 1;
        for ($year = $startYear; $year <= $endYear; $year++, $programYear++) {
            $semesters = [];
            $yearCompleted = 0.0;
            $yearPending = 0.0;

            foreach (AcademicSettingsService::SEMESTERS as $semester) {
                $slot = $this->statsFromSlot($allocation[$year][$semester] ?? []);
                $semesters[] = [
                    'semester' => $semester,
                    'approved' => $slot['approved'],
                    'pending' => $slot['pending'],
                    'remaining' => $slot['remaining'],
                    'required' => $required,
                    'is_current' => $year === (int) $period->year_start && $semester === $period->semester,
                ];
                $yearCompleted += $slot['approved'];
                $yearPending += $slot['pending'];
            }

            $years[] = [
                'program_year' => $programYear,
                'year_start' => $year,
                'year_end' => $year + 1,
                'academic_year' => 'AY '.$year.'–'.($year + 1),
                'completed' => round($yearCompleted, 2),
                'pending' => round($yearPending, 2),
                'semesters' => $semesters,
                'is_current' => $year === (int) $period->year_start,
            ];
        }

        return [
            'required' => $required,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'needs_start_year' => false,
            'year_start' => (int) $period->year_start,
            'year_end' => (int) $period->year_start + 1,
            'academic_year' => 'AY '.$period->year_start.'–'.((int) $period->year_start + 1),
            'years' => $years,
            'current' => $hourStats,
            'period' => $this->academic->infoForUser($user, $hourStats),
        ];
    }

    /**
     * Current Year 1–4 placement from Start Academic Year vs the recording year.
     * Returns 0 when Start Academic Year has not been saved.
     */
    public function programYearForScholar(User $user): int
    {
        $start = $this->startingAcademicYear($user);
        if ($start === null) {
            return 0;
        }

        $reference = (int) $this->academic->current()->year_start;

        return max(1, min(4, $reference - $start + 1));
    }

    /**
     * Approved / pending / remaining for one academic year (and optional
     * semester) using the records' own stamps. Other years are ignored, so
     * historical reports stay accurate. Each selected semester is capped at
     * REQUIRED_HOURS. Pending hours never count as completed.
     *
     * @param  iterable<int, object>  $records
     * @param  array{year_start: int, semester: string}  $filter
     * @return array<string, float>
     */
    public function periodHourStatsFromRecords(iterable $records, array $filter): array
    {
        $requiredEach = (float) self::REQUIRED_HOURS;
        $year = (int) $filter['year_start'];
        $semesters = (($filter['semester'] ?? AcademicSettingsService::SEMESTER_ALL) === AcademicSettingsService::SEMESTER_ALL)
            ? AcademicSettingsService::SEMESTERS
            : [(string) $filter['semester']];

        $approved = 0.0;
        $pending = 0.0;

        foreach ($semesters as $semester) {
            $semesterApproved = 0.0;
            $semesterPending = 0.0;

            foreach ($records as $row) {
                if ((int) ($row->academic_year_start ?? 0) !== $year) {
                    continue;
                }
                if ((string) ($row->semester ?? '') !== $semester) {
                    continue;
                }

                $hours = (float) ($row->hours_earned ?? 0);
                if ($row->status === Attendance::STATUS_APPROVED) {
                    $semesterApproved += $hours;
                } elseif ($row->status === Attendance::STATUS_PENDING) {
                    $semesterPending += $hours;
                }
            }

            $approved += min($requiredEach, $semesterApproved);
            $pending += $semesterPending;
        }

        $required = $requiredEach * count($semesters);
        $approved = round($approved, 2);
        $pending = round($pending, 2);

        return [
            'approved' => $approved,
            'pending' => $pending,
            'required' => $required,
            'remaining' => (float) max(0, round($required - $approved, 2)),
        ];
    }

    public static function programYearIndex(?string $yearLevel): int
    {
        $raw = strtolower(trim((string) $yearLevel));
        if ($raw === '') {
            return 1;
        }

        if (preg_match('/\b(4th|fourth|year\s*4|iv)\b/', $raw)) {
            return 4;
        }
        if (preg_match('/\b(3rd|third|year\s*3|iii)\b/', $raw)) {
            return 3;
        }
        if (preg_match('/\b(2nd|second|year\s*2|ii)\b/', $raw)) {
            return 2;
        }

        return 1;
    }

    public function dashboardStats(User $user, ?array $hours = null, ?int $unreadNotifications = null): array
    {
        $hours = $hours ?? $this->serviceHourStats($user);

        return [
            'service_hours' => [
                'value' => $hours['approved'].' / '.$hours['required'],
                'completed' => $hours['approved'],
                'required' => $hours['required'],
            ],
            'upcoming_events' => $this->countUpcomingEventsForPeriod($user),
            'pending_attendances' => $this->academic
                ->scopeAttendancesForPeriod(
                    $user->attendances()->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in'),
                    $this->academic->selectedPeriod()
                )
                ->count(),
            'notifications' => $unreadNotifications ?? $user->unreadNotificationCount(),
        ];
    }

    private function countUpcomingEventsForPeriod(User $user): int
    {
        $weekStart = now()->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $weekEnd = now()->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay();
        $query = Event::query()
            ->overlappingDates($weekStart, $weekEnd)
            ->notEnded()
            ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED]);
        $this->scopeEventsForSelectedPeriod($query);

        return $this->scopeEventsForUser($query, $user)->count();
    }

    public function upcomingEvents(User $user, int $limit = 10): Collection
    {
        $attendances = $this->attendancesByEvent($user);

        $query = Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
            ->notEnded()
            ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit($limit);
        $this->scopeEventsForSelectedPeriod($query);

        return $this->scopeEventsForUser($query, $user)->get()
            ->map(fn (Event $event) => $this->formatEvent($event, $user, $attendances));
    }

    public function nextUpcomingEvent(User $user): ?array
    {
        $attendances = $this->attendancesByEvent($user);

        $registered = Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
            ->notEnded()
            ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED])
            ->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('starts_at')
            ->orderBy('id');
        $this->scopeEventsForSelectedPeriod($registered);

        $event = $this->scopeEventsForUser($registered, $user)->first();

        if (! $event) {
            $open = Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->notEnded()
                ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED])
                ->orderBy('starts_at')
                ->orderBy('id');
            $this->scopeEventsForSelectedPeriod($open);
            $event = $this->scopeEventsForUser($open, $user)->first();
        }

        return $event ? $this->formatEvent($event, $user, $attendances) : null;
    }

    public function eventsForUser(User $user, ?string $search = null, ?string $status = null): Collection
    {
        $base = Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('starts_at')
            ->orderBy('id');

        // Active Events are events that have not passed their saved end time.
        // Status filters still return matching records, including past dates.
        if ($status === null || $status === 'upcoming') {
            $base->notEnded();
        }

        $this->scopeEventsForSelectedPeriod($base);

        $query = $this->scopeEventsForUser($base, $user);

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
                    return ! $event['is_past']
                        && ! in_array($event['event_status'], [
                            Event::STATUS_CANCELLED,
                            Event::STATUS_COMPLETED,
                        ], true);
                }

                if (in_array($status, [
                    Event::STATUS_PENDING,
                    Event::STATUS_CONFIRMED,
                    Event::STATUS_CANCELLED,
                    Event::STATUS_COMPLETED,
                ], true)) {
                    $eventStatus = $event['event_status'];
                    if ($status === Event::STATUS_CONFIRMED) {
                        return in_array($eventStatus, [Event::STATUS_CONFIRMED, Event::STATUS_ONGOING], true);
                    }

                    return $eventStatus === $status;
                }

                return $event['calendar_status'] === $status;
            })->values();
        }

        return $formatted;
    }

    public function formatEvent(Event $event, User $user, ?Collection $attendances = null): array
    {
        $registration = $this->registrationFor($event, $user);
        $attendance = $attendances
            ? $attendances->get($event->id)
            : $user->attendances()->where('event_id', $event->id)->first();

        $attendance = $this->applyMissedCheckIn($event, $user, $registration, $attendance);
        if ($attendance && ! $attendance->relationLoaded('event')) {
            $attendance->setRelation('event', $event);
        }
        $hasParticipated = $this->hasParticipated($attendance);
        $calendarStatus = $this->calendarStatus($event, $user, $attendance, $registration);
        $isCancelled = $event->isCancelled();
        $isCompleted = $event->status === Event::STATUS_COMPLETED;
        $isPending = $event->isPendingApproval();
        $canRegister = $event->allowsScholarRegistration()
            && ($registration?->status ?? 'not_joined') === 'not_joined';

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'date' => $event->starts_at->copy()->timezone(config('app.timezone'))->format('M d'),
            'month' => strtoupper($event->starts_at->copy()->timezone(config('app.timezone'))->format('M')),
            'day' => $event->starts_at->copy()->timezone(config('app.timezone'))->format('d'),
            'dow' => strtoupper($event->starts_at->copy()->timezone(config('app.timezone'))->format('D')),
            'time' => $event->scheduleTimeLabel(),
            'full_date' => $event->scheduleDateLabel(),
            'hours' => $event->service_hours,
            'organizer' => $event->organizer,
            'image_url' => $event->image_url,
            'registration_status' => $registration?->status ?? 'not_joined',
            'has_participated' => $hasParticipated,
            'event_status' => $event->status,
            'calendar_status' => $calendarStatus,
            'status_label' => $event->statusLabel(),
            'status_class' => $this->lifecycleStatusClass($event),
            'participation_label' => $this->eventStatusLabel($calendarStatus),
            'participation_class' => $this->eventStatusClass($calendarStatus),
            'failed_to_check_in' => $calendarStatus === 'failed_to_check_in',
            'is_cancelled' => $isCancelled,
            'is_pending' => $isPending,
            'is_completed' => $isCompleted,
            'can_register' => $canRegister,
            'registration_blocked_message' => $isPending
                ? 'Registration is unavailable because this event is pending approval. Please wait until the event is confirmed.'
                : ($isCancelled
                    ? 'This event has been cancelled and is no longer available for registration or attendance.'
                    : ($isCompleted
                        ? 'This event is completed and is no longer open for registration.'
                        : null)),
            'calendar_days' => $event->calendarDateKeys(),
            'day_times' => collect($event->calendarDateKeys())
                ->mapWithKeys(fn (string $day) => [$day => $event->calendarDayTimeLabel($day)])
                ->all(),
            'attendance' => $attendance,
            'attendance_open' => $event->allowsScholarAttendanceActions(),
            'attendance_status' => $event->attendanceStatusLabel(),
            'attendance_opened_at' => $event->attendanceOpenedAtLabel(),
            'attendance_closed_at' => $event->attendanceClosedAtLabel(),
            'attendance_message' => $this->attendanceSessionMessage($event),
            'can_check_in' => $event->allowsScholarAttendanceActions()
                && $registration
                && in_array($registration->status, [
                    EventRegistration::STATUS_CONFIRMED,
                    EventRegistration::STATUS_FAILED_CHECK_IN,
                ], true)
                && ! $attendance?->hasCheckedIn(),
            'can_check_out' => $event->allowsScholarAttendanceActions()
                && ($attendance?->scholarCanCheckOut() ?? false),
            'can_modify_attendance' => $event->allowsScholarAttendanceActions()
                && ($attendance?->scholarCanModify() ?? false),
            'is_past' => $event->hasEnded(),
        ];
    }

    public function lifecycleStatusClass(Event $event): string
    {
        return match ($event->status) {
            Event::STATUS_PENDING => 'pending',
            Event::STATUS_CONFIRMED, Event::STATUS_ONGOING => 'confirmed',
            Event::STATUS_CANCELLED => 'cancelled',
            Event::STATUS_COMPLETED => 'completed',
            default => 'not-joined',
        };
    }

    public function hasParticipated(?Attendance $attendance): bool
    {
        return $attendance !== null
            && $attendance->status === Attendance::STATUS_APPROVED
            && $attendance->hasCheckedIn();
    }

    public function attendanceSessionMessage(Event $event): string
    {
        if ($event->isPendingApproval()) {
            return 'Registration is unavailable because this event is pending approval. Please wait until the event is confirmed.';
        }

        if ($event->isCancelled()) {
            return 'This event has been cancelled and is no longer available for registration or attendance.';
        }

        if ($event->status === Event::STATUS_COMPLETED) {
            return 'This event is completed and is no longer open for registration or attendance.';
        }

        $eventStamp = sprintf(
            '%s on %s, %s',
            $event->title,
            $event->scheduleDateLabel(),
            $event->scheduleTimeLabel()
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
        if ($event->isPendingApproval() || $event->isCancelled() || $event->status === Event::STATUS_COMPLETED) {
            return false;
        }

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

        $stamp = $this->academic->preservedAttendanceStamp($attendance);

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
        $registration ??= $this->registrationFor($event, $user);

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

        $attendance = $attendance->fresh(['user', 'event']);

        try {
            $this->attendanceDrive->storeApprovedPhoto($attendance);
        } catch (Throwable $e) {
            Log::warning('Verified attendance was approved but the BSSA Attendance Drive upload failed.', [
                'attendance_id' => $attendance->id,
                'message' => $e->getMessage(),
            ]);
            app(WorkflowMailService::class)->alertAdministrators(
                'attendance-drive:'.$attendance->id,
                'Google Drive attendance upload failed',
                "Attendance for {$attendance->event?->title} was approved, but the participation photo could not be stored in Google Drive. Reconnect Google Drive from Settings and review that attendance record."
            );
        }

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

        if (filled($attendance->google_drive_file_id)) {
            try {
                $this->attendanceDrive->removeStoredPhoto($attendance);
            } catch (Throwable $e) {
                Log::warning('Could not remove a BSSA Attendance Drive file after rejection.', [
                    'attendance_id' => $attendance->id,
                    'message' => $e->getMessage(),
                ]);
            }
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
        return $this->registrationFor($event, $user)?->status ?? 'not_joined';
    }

    /**
     * The registration for this scholar on this event. A loaded relation may
     * contain every participant, so it is never matched by list position.
     */
    private function registrationFor(Event $event, User $user): ?EventRegistration
    {
        if ($event->relationLoaded('registrations')) {
            $match = $event->registrations->first(
                fn ($registration) => (int) $registration->user_id === (int) $user->id
            );

            if ($match) {
                return $match;
            }
        }

        return $user->eventRegistrations()->where('event_id', $event->id)->first();
    }

    public function calendarEvents(User $user, int $year, int $month): Collection
    {
        $monthDate = Carbon::create($year, $month, 1);
        $start = $monthDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end = $monthDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->endOfDay();
        $attendances = $this->attendancesByEvent($user);

        $query = Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
            ->overlappingDates($start, $end)
            ->orderBy('starts_at')
            ->orderBy('id');
        $this->scopeEventsForSelectedPeriod($query);

        return $this->scopeEventsForUser($query, $user)->get()
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
        $deadline = $type->deadlineLabel();
        $body = $type->description
            ? "Scholar Staff posted a required document: {$type->name}. {$type->description} Please submit it on your Documents page. Deadline: {$deadline}."
            : "Scholar Staff posted a required document: {$type->name}. Please submit it on your Documents page. Deadline: {$deadline}.";

        $count = 0;

        $scholarsQuery->each(function (User $scholar) use ($title, $body, &$count) {
            $existing = ScholarNotification::query()
                ->where('user_id', $scholar->id)
                ->where('title', $title)
                ->first();
            $changed = ! $existing || $existing->body !== $body;

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

            if ($changed) {
                app(WorkflowMailService::class)->sendForNotification($scholar, $title, $body, 'documents');
            }
        });

        return $count;
    }

    /**
     * Close document types whose deadline has passed, and notify scholars once
     * when a deadline is approaching or has closed submissions.
     *
     * @param  list<int>  $programIds
     */
    public function syncDocumentDeadlines(array $programIds = []): void
    {
        $now = now();
        $soon = $now->copy()->addDay();

        $types = DocumentType::query()
            ->when($programIds !== [], fn ($query) => $query->whereIn('scholarship_program_id', $programIds))
            ->whereNotNull('submission_deadline')
            ->get();

        foreach ($types as $type) {
            if ($type->submission_deadline->lte($now) && $type->submissionsAreOpen() && ! $type->reopenedAfterDeadline()) {
                $type->update([
                    'submissions_open' => false,
                    'submissions_closed_at' => $now,
                ]);
                $type->refresh();
                $this->notifyDocumentWindow($type, 'deadline_passed');

                continue;
            }

            if ($type->acceptsSubmissions() && $type->submission_deadline->gt($now) && $type->submission_deadline->lte($soon)) {
                $this->notifyDocumentWindow($type, 'approaching');
            }
        }
    }

    public function notifyDocumentWindow(DocumentType $type, string $event): int
    {
        $name = $type->name;
        $deadline = $type->deadlineLabel();
        $when = now()->timezone(config('app.timezone'))->format('M j, Y g:i A');

        [$title, $body] = match ($event) {
            'approaching' => [
                "Deadline approaching: {$name}",
                "The deadline for {$name} is {$deadline}. Submit it from your Documents page before then.",
            ],
            'deadline_passed' => [
                "Deadline passed: {$name}",
                "Submissions for {$name} closed because the deadline passed ({$deadline}).",
            ],
            'closed' => [
                "Submissions closed: {$name}",
                "Scholar Staff closed submissions for {$name} on {$when}. You cannot upload or replace this document until submissions are opened again.",
            ],
            'reopened' => [
                "Submissions reopened: {$name}",
                "Scholar Staff reopened submissions for {$name} on {$when}. Deadline: {$deadline}.",
            ],
            'deadline_extended' => [
                "Deadline extended: {$name}",
                "The deadline for {$name} was extended to {$deadline}.",
            ],
            'deadline_changed' => [
                "Deadline updated: {$name}",
                "The deadline for {$name} is now {$deadline}.",
            ],
            'deadline_removed' => [
                "Deadline removed: {$name}",
                "The deadline for {$name} was removed. No deadline set.",
            ],
            'deadline_set' => [
                "Deadline set: {$name}",
                "A deadline was set for {$name}: {$deadline}.",
            ],
            default => [null, null],
        };

        if ($title === null || $body === null) {
            return 0;
        }

        return $this->notifyProgramScholarsOnce($type, $title, $body);
    }

    private function notifyProgramScholarsOnce(DocumentType $type, string $title, string $body): int
    {
        if (! $type->scholarship_program_id) {
            return 0;
        }

        $count = 0;

        User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $type->scholarship_program_id)
            ->orderBy('id')
            ->each(function (User $scholar) use ($title, $body, &$count) {
                $exists = ScholarNotification::query()
                    ->where('user_id', $scholar->id)
                    ->where('title', $title)
                    ->where('body', $body)
                    ->exists();

                if ($exists) {
                    return;
                }

                ScholarNotification::create([
                    'user_id' => $scholar->id,
                    'title' => $title,
                    'body' => $body,
                    'category' => 'documents',
                    'is_important' => true,
                ]);
                app(WorkflowMailService::class)->sendForNotification($scholar, $title, $body, 'documents');
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

        app(WorkflowMailService::class)->sendForNotification($user, $title, $body, $category, $eventId);
    }

    /**
     * Keep each scholar's registration aligned with an explicit staff status change.
     * Confirming the event confirms pending registrations. Returning it to Pending
     * does not confirm anyone, and does not reopen attendance.
     */
    public function syncRegistrationsWithEventStatus(Event $event): void
    {
        if (in_array($event->status, [Event::STATUS_CONFIRMED, Event::STATUS_ONGOING], true)) {
            EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', EventRegistration::STATUS_PENDING)
                ->update(['status' => EventRegistration::STATUS_CONFIRMED]);

            return;
        }

        if ($event->status !== Event::STATUS_PENDING) {
            return;
        }

        $checkedInUserIds = Attendance::query()
            ->where('event_id', $event->id)
            ->whereNotNull('check_in')
            ->pluck('user_id');

        EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->when($checkedInUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $checkedInUserIds))
            ->update(['status' => EventRegistration::STATUS_PENDING]);
    }

    public function notifyScholarsOfPublishedEvent(Event $event): int
    {
        return $this->notifyScholarsOfEventAction(
            $event,
            'published',
            'New Event: '.$event->title,
            sprintf(
                '%s has been scheduled for %s, %s (%s). Service hours: %s. Open Events to view details and register.',
                $event->title,
                $event->scheduleDateLabel(),
                $event->scheduleTimeLabel(),
                $event->location,
                number_format((float) $event->service_hours, 1).' hrs'
            ),
            true
        );
    }

    public function notifyScholarsOfEventStatusChange(Event $event, string $status): int
    {
        $status = strtolower(trim($status));

        return match ($status) {
            Event::STATUS_PENDING => $this->notifyScholarsOfEventAction(
                $event,
                'pending',
                'Event Pending: '.$event->title,
                sprintf(
                    '%s is now Pending. Scheduled %s, %s (%s). Check Events for the latest status.',
                    $event->title,
                    $event->scheduleDateLabel(),
                    $event->scheduleTimeLabel(),
                    $event->location
                ),
                false
            ),
            Event::STATUS_CONFIRMED => $this->notifyScholarsOfEventAction(
                $event,
                'confirmed',
                'Event Confirmed: '.$event->title,
                sprintf(
                    '%s is Confirmed for %s, %s (%s). Open Events to register or review details.',
                    $event->title,
                    $event->scheduleDateLabel(),
                    $event->scheduleTimeLabel(),
                    $event->location
                ),
                true
            ),
            Event::STATUS_CANCELLED => $this->notifyScholarsOfEventAction(
                $event,
                'cancelled',
                'Event Cancelled: '.$event->title,
                sprintf(
                    '%s scheduled for %s, %s has been Cancelled. It is no longer available for registration or attendance.',
                    $event->title,
                    $event->scheduleDateLabel(),
                    $event->scheduleTimeLabel()
                ),
                true
            ),
            Event::STATUS_COMPLETED => $this->notifyScholarsOfEventAction(
                $event,
                'completed',
                'Event Completed: '.$event->title,
                sprintf(
                    '%s has been marked Completed. Review Events or Service Hours for your participation record.',
                    $event->title
                ),
                false
            ),
            default => 0,
        };
    }

    public function notifyScholarsOfCompletedEvents(array $eventIds): int
    {
        if ($eventIds === []) {
            return 0;
        }

        $total = 0;

        Event::query()
            ->whereIn('id', $eventIds)
            ->where('status', Event::STATUS_COMPLETED)
            ->each(function (Event $event) use (&$total) {
                $total += $this->notifyScholarsOfEventStatusChange($event, Event::STATUS_COMPLETED);
            });

        return $total;
    }

    /**
     * Deduplicated in-app notification for a staff/admin event lifecycle action.
     * Same user + event + action title is updated in place (no duplicate rows).
     */
    public function notifyScholarsOfEventAction(
        Event $event,
        string $actionKey,
        string $title,
        string $body,
        bool $important = false
    ): int {
        if (! $event->scholarship_program_id) {
            return 0;
        }

        $scholarsQuery = $this->scholarsQueryForEvent($event);
        $count = 0;

        $scholarsQuery->each(function (User $scholar) use ($event, $title, $body, $important, &$count) {
            if (! $event->isVisibleAfter($scholar->eventsVisibleFrom())) {
                return;
            }

            $existing = ScholarNotification::query()
                ->where('user_id', $scholar->id)
                ->where('event_id', $event->id)
                ->where('title', $title)
                ->first();
            $changed = ! $existing || $existing->body !== $body;

            ScholarNotification::updateOrCreate(
                [
                    'user_id' => $scholar->id,
                    'event_id' => $event->id,
                    'title' => $title,
                ],
                [
                    'body' => $body,
                    'category' => 'event_reminder',
                    'is_important' => $important,
                    'is_read' => false,
                ]
            );

            if ($changed) {
                app(WorkflowMailService::class)->sendForNotification($scholar, $title, $body, 'event_reminder', $event->id);
            }

            $count++;
        });

        return $count;
    }

    /**
     * Approved scholars in the event's program (scoped to the acting staff club when present).
     */
    public function scholarsQueryForEvent(Event $event): Builder
    {
        $scholarsQuery = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $event->scholarship_program_id);

        $staff = auth()->user();
        if ($staff?->isScholarStaff() && $staff->scholarship_club_id) {
            $scholarsQuery->where('scholarship_club_id', $staff->scholarship_club_id);
        }

        return $scholarsQuery;
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
