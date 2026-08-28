<?php

namespace App\Services;

use App\Models\Announcement;
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
use Illuminate\Support\Facades\Storage;

class ScholarService
{
    public const REQUIRED_HOURS = 30;

    public function __construct(private AcademicSettingsService $academic) {}

    public function scopeEventsForUser(Builder $query, User $user): Builder
    {
        $query->whereIn('status', ['confirmed', 'upcoming', 'ongoing', 'completed', 'pending']);

        if (! $user->scholarship_program_id) {
            return $query;
        }

        $locationIds = $user->visibleLocationIds();

        if ($locationIds) {
            return $query->whereIn('scholarship_program_id', $locationIds);
        }

        return $query->whereNull('scholarship_program_id');
    }

    public function scopeAnnouncementsForUser(Builder $query, User $user): Builder
    {
        if (! $user->scholarship_program_id) {
            return $query->whereNull('scholarship_program_id');
        }

        $locationIds = $user->visibleLocationIds();

        if ($locationIds) {
            return $query->whereIn('scholarship_program_id', $locationIds);
        }

        return $query->whereNull('scholarship_program_id');
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

    public function currentSemesterInfo(User $user): array
    {
        $hours = $this->serviceHourStats($user);

        return $this->academic->infoForUser($user, $hours);
    }

    public function serviceHourStats(User $user): array
    {
        $period = $this->academic->forUser($user);
        $query = $this->academic->scopeAttendancesForPeriod($user->attendances(), $period);

        $approved = (float) (clone $query)
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->sum('hours_earned');
        $pending = (float) (clone $query)
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->sum('hours_earned');
        $required = self::REQUIRED_HOURS;
        $remaining = max(0, $required - $approved);

        return compact('approved', 'pending', 'required', 'remaining');
    }

    public function semesterSummaryChart(User $user): array
    {
        $stats = $this->serviceHourStats($user);
        $required = max(1, $stats['required']);

        $approvedArc = min(100, round(($stats['approved'] / $required) * 100));
        $pendingArc = min(100 - $approvedArc, round(($stats['pending'] / $required) * 100));
        $remainingArc = max(0, 100 - $approvedArc - $pendingArc);

        return [
            'approved' => $stats['approved'],
            'pending' => $stats['pending'],
            'remaining' => $stats['remaining'],
            'required' => $stats['required'],
            'completed_pct' => round(($stats['approved'] / $required) * 100),
            'pending_pct' => round(($stats['pending'] / $required) * 100),
            'remaining_pct' => round(($stats['remaining'] / $required) * 100),
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
        $required = self::REQUIRED_HOURS;
        $maxScale = 40;

        $semesters = collect(AcademicSettingsService::SEMESTERS)->map(function (string $semester) use ($user, $period, $required, $maxScale) {
            $approved = (float) $user->attendances()
                ->where('academic_year_start', $period->year_start)
                ->where('academic_year_end', $period->year_end)
                ->where('semester', $semester)
                ->where('status', Attendance::STATUS_APPROVED)
                ->whereNotNull('check_in')
                ->sum('hours_earned');

            return [
                'label' => $semester,
                'short_label' => str_replace(' Semester', '', $semester),
                'approved' => $approved,
                'required' => $required,
                'bar_height' => min(100, round(($approved / $maxScale) * 100)),
                'required_line' => round(($required / $maxScale) * 100),
            ];
        })->all();

        return [
            'required' => $required,
            'max_scale' => $maxScale,
            'semesters' => $semesters,
            'required_line' => round(($required / $maxScale) * 100),
        ];
    }

    public function dashboardStats(User $user): array
    {
        $hours = $this->serviceHourStats($user);

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
            'notifications' => $user->scholarNotifications()->where('is_read', false)->count(),
        ];
    }

    public function upcomingEvents(User $user, int $limit = 10): Collection
    {
        return $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->where('starts_at', '>=', now()->subDay())
                ->orderBy('starts_at')
                ->limit($limit),
            $user
        )->get()
            ->map(fn (Event $event) => $this->formatEvent($event, $user, $user->attendances()->get()->keyBy('event_id')));
    }

    public function nextUpcomingEvent(User $user): ?array
    {
        $attendances = $user->attendances()->get()->keyBy('event_id');

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
        $attendances = $user->attendances()->get()->keyBy('event_id');
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
            'can_check_in' => $event->isHappeningNow()
                && $registration
                && $registration->status === EventRegistration::STATUS_CONFIRMED,
            'is_past' => $event->hasEnded(),
        ];
    }

    public function hasParticipated(?Attendance $attendance): bool
    {
        return $attendance !== null
            && $attendance->status === Attendance::STATUS_APPROVED
            && $attendance->hasCheckedIn();
    }

    public function missedCheckIn(Event $event, ?EventRegistration $registration, ?Attendance $attendance): bool
    {
        if (! $event->hasEnded()) {
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
                'remarks' => 'Did not check in during the event. No service hours credited.',
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
        $registrations = EventRegistration::query()
            ->with('event')
            ->where('user_id', $user->id)
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_FAILED_CHECK_IN])
            ->whereHas('event', fn ($q) => $q->where('ends_at', '<', now()))
            ->get();

        $attendances = $user->attendances()->get()->keyBy('event_id');

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
        $registrations = EventRegistration::query()
            ->with(['event', 'user'])
            ->whereIn('status', [EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_FAILED_CHECK_IN])
            ->whereHas('event', fn ($q) => $q->where('ends_at', '<', now()))
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

        return $this->scopeEventsForUser(
            Event::with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->overlappingDates($start, $end)
                ->orderBy('starts_at'),
            $user
        )->get()
            ->map(fn (Event $event) => $this->formatEvent($event, $user, $user->attendances()->get()->keyBy('event_id')));
    }

    public function ensureUserDocuments(User $user): void
    {
        $types = DocumentType::all();

        foreach ($types as $type) {
            Document::firstOrCreate(
                ['user_id' => $user->id, 'document_type_id' => $type->id],
                ['status' => 'not_submitted']
            );
        }
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

    public function documentOverviewStats(User $user): array
    {
        $documents = $this->documentsForUser($user);
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
        $count = 0;

        User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->chunkById(100, function ($scholars) use ($type, &$count) {
                foreach ($scholars as $scholar) {
                    Document::firstOrCreate(
                        ['user_id' => $scholar->id, 'document_type_id' => $type->id],
                        ['status' => 'not_submitted']
                    );
                    $count++;
                }
            });

        return $count;
    }

    public function notifyScholarsOfDocumentType(DocumentType $type, array $programIds = []): int
    {
        $scholarsQuery = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED);

        if ($programIds) {
            $scholarsQuery->whereIn('scholarship_program_id', $programIds);
        }

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

    public function notify(User $user, string $title, string $body, string $category = 'system', bool $important = false): void
    {
        ScholarNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'is_important' => $important,
        ]);
    }

    public function notifyScholarsOfPublishedEvent(Event $event): int
    {
        $scholarsQuery = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED);

        if ($event->scholarship_program_id) {
            $scholarsQuery->where('scholarship_program_id', $event->scholarship_program_id);
        }

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

        $scholarsQuery->each(function (User $scholar) use ($title, $body, &$count) {
            ScholarNotification::updateOrCreate(
                [
                    'user_id' => $scholar->id,
                    'title' => $title,
                ],
                [
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
}
