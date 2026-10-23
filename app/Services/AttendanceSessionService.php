<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceDaySession;
use App\Models\AttendanceSessionLog;
use App\Models\Event;
use Carbon\Carbon;
use App\Models\EventRegistration;
use App\Models\ScholarNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceSessionService
{
    public function __construct(private ScholarService $scholar) {}

    public function open(Event $event, User $staff, ?string $sessionDate = null): Event
    {
        if ($event->isAttendanceOpen()) {
            throw new \InvalidArgumentException('Attendance is already open for this event.');
        }

        $now = now();

        DB::transaction(function () use ($event, $staff, $now) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new \InvalidArgumentException('Event not found.');
            }

            if ($locked->isAttendanceOpen()) {
                throw new \InvalidArgumentException('Attendance is already open for this event.');
            }

            $locked->update([
                'attendance_is_open' => true,
                'attendance_opened_at' => $now,
                'attendance_opened_by' => $staff->id,
            ]);

            $this->writeSessionLog($locked, $staff, AttendanceSessionLog::ACTION_OPENED, null, $now);
        });

        $event = $event->fresh();
        $this->scholar->restoreCheckInEligibility($event);
        $this->notifyScholars($event, AttendanceSessionLog::ACTION_OPENED);

        return $event;
    }

    public function close(Event $event, User $staff, ?string $sessionDate = null): Event
    {
        $lockKey = 'attendance.close.'.$event->id;
        if (! Cache::add($lockKey, 1, 15)) {
            $current = $event->fresh() ?? $event;
            if (! $current->isAttendanceOpen()) {
                return $current;
            }

            throw new \InvalidArgumentException('Attendance is already being closed for this event.');
        }

        try {
            $closed = DB::transaction(function () use ($event, $staff) {
                $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first();

                if (! $locked) {
                    throw new \InvalidArgumentException('Event not found.');
                }

                if (! $locked->isAttendanceOpen()) {
                    return $locked;
                }

                $now = now();
                $locked->update([
                    'attendance_is_open' => false,
                    'attendance_closed_at' => $now,
                    'attendance_closed_by' => $staff->id,
                ]);

                $this->writeSessionLog($locked, $staff, AttendanceSessionLog::ACTION_CLOSED, null, $now);

                return $locked->fresh();
            });

            if (! $closed->isAttendanceOpen() && $closed->attendanceSessionClosed()) {
                $eventId = (int) $closed->id;
                dispatch(function () use ($eventId) {
                    try {
                        $fresh = Event::query()->find($eventId);
                        if (! $fresh || $fresh->isAttendanceOpen() || ! $fresh->attendanceSessionClosed()) {
                            return;
                        }

                        app(AttendanceSessionService::class)->completeClosedSession($fresh);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                })->afterResponse();
            }

            return $closed;
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function completeClosedSession(Event $event): void
    {
        if ($event->isAttendanceOpen() || ! $event->attendanceSessionClosed()) {
            return;
        }

        $this->scholar->syncMissedCheckInsForEvent($event);
        $this->notifyScholars($event, AttendanceSessionLog::ACTION_CLOSED);
    }

    public function resolveSessionDate(Event $event, ?string $sessionDate): string
    {
        $dates = $event->calendarDateKeys();
        if ($dates === []) {
            throw new \InvalidArgumentException('This event does not have a start and end date.');
        }

        if ($sessionDate !== null && $sessionDate !== '') {
            $normalized = Carbon::parse($sessionDate)->timezone(config('app.timezone'))->toDateString();
            if (! in_array($normalized, $dates, true)) {
                throw new \InvalidArgumentException('Choose an event date between the start and end dates.');
            }

            return $normalized;
        }

        $today = now()->timezone(config('app.timezone'))->toDateString();

        return in_array($today, $dates, true) ? $today : $dates[0];
    }

    /**
     * @return list<array{date: string, date_label: string, is_open: bool, status: string, opened_at: string, closed_at: string, last_opened_at: string}>
     */
    public function dayPanels(Event $event): array
    {
        $sessions = $event->daySessions()->get()->keyBy(
            fn (AttendanceDaySession $session) => $session->session_date?->toDateString()
        );

        $panels = [];
        foreach ($event->calendarDateKeys() as $date) {
            $session = $sessions->get($date);
            $opened = $session?->opened_at;
            $closed = $session?->closed_at;
            $isOpen = (bool) $session?->is_open;
            $openedLabel = $opened ? $event->timestampLabel($opened) : 'Not yet opened';
            $currentCycleOpen = $isOpen || ($opened && $closed && $closed->lt($opened));
            $closedLabel = (! $opened || $currentCycleOpen || ! $closed)
                ? 'Not yet closed'
                : $event->timestampLabel($closed);

            $panels[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date, config('app.timezone'))->format('F j, Y'),
                'is_open' => $isOpen,
                'status' => $isOpen ? 'OPEN' : 'CLOSED',
                'opened_at' => $openedLabel,
                'closed_at' => $closedLabel,
                'last_opened_at' => $openedLabel,
            ];
        }

        return $panels;
    }

    /**
     * @return list<string>
     */
    public function historyLines(Event $event): array
    {
        return $event->attendanceSessionLogs()
            ->orderBy('acted_at')
            ->orderBy('id')
            ->get()
            ->map(function (AttendanceSessionLog $log) use ($event) {
                $when = $event->timestampLabel($log->acted_at);
                $verb = $log->action === AttendanceSessionLog::ACTION_OPENED ? 'Opened' : 'Closed';
                $date = $log->session_date
                    ? Carbon::parse($log->session_date->toDateString(), config('app.timezone'))->format('F j, Y')
                    : null;

                return $date
                    ? "{$verb} the {$date} session at {$when}."
                    : "{$verb} at {$when}.";
            })
            ->all();
    }

    private function defaultOpenDate(Event $event): ?string
    {
        $open = $event->daySessions()->where('is_open', true)->orderBy('session_date')->first();
        if ($open?->session_date) {
            return $open->session_date->toDateString();
        }

        if ($event->isAttendanceOpen()) {
            return $this->resolveSessionDate($event, null);
        }

        return null;
    }

    private function daySession(Event $event, string $date, bool $lock = false): ?AttendanceDaySession
    {
        $query = AttendanceDaySession::query()
            ->where('event_id', $event->id)
            ->whereDate('session_date', $date);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function writeSessionLog(Event $event, User $staff, string $action, ?string $date, $actedAt): void
    {
        AttendanceSessionLog::create([
            'event_id' => $event->id,
            'session_date' => $date,
            'staff_id' => $staff->id,
            'action' => $action,
            'acted_at' => $actedAt,
        ]);
    }

    public function sessionPayload(Event $event): array
    {
        $opened = $event->attendanceOpenedAtLabel();
        $closed = $event->attendanceClosedAtLabel();
        $historyParts = [];

        if ($opened) {
            $historyParts[] = 'Last opened: '.$opened;
        }
        if ($closed) {
            $historyParts[] = 'Last closed: '.$closed;
        }

        if ($event->isAttendanceOpen()) {
            $copy = 'Opened '.($opened ?? 'just now').'. Scholars can mark attendance until you click Close Attendance. This session will not close automatically.';
        } elseif ($event->attendanceMayBeFinalized() && $event->attendanceSessionClosed()) {
            $copy = 'Closed '.$closed.'. Scholars can no longer submit or modify their attendance. You can still edit records below.';
        } else {
            $copy = 'Closed. Scholars cannot mark attendance until you click Open Attendance. The event schedule does not open or close this session.';
        }

        return [
            'event_id' => $event->id,
            'is_open' => $event->isAttendanceOpen(),
            'status_label' => $event->attendanceStatusLabel(),
            'status_badge_class' => $event->attendanceStatusBadgeClass(),
            'opened_at' => $opened,
            'closed_at' => $closed,
            'copy' => $copy,
            'history' => implode(' · ', $historyParts),
        ];
    }

    public function sessionsForUser(User $user, int $limit = 8): Collection
    {
        $attendances = $user->attendances()->get()->keyBy('event_id');
        $now = now();

        return $this->scholarAttendanceEventsQuery($user, $now)
            ->orderByRaw('case when attendance_is_open = 1 then 0 else 1 end')
            ->orderByRaw('case when starts_at <= ? and ends_at >= ? then 0 else 1 end', [$now, $now])
            ->orderBy('ends_at')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => $this->formatSession(
                $event,
                $user,
                $event->registrations->first(),
                $attendances->get($event->id)
            ));
    }

    public function liveStatusForUser(User $user, ?int $focusEventId = null): array
    {
        $now = now();
        $events = $this->scholarAttendanceEventsQuery($user, $now)
            ->orderBy('ends_at')
            ->limit(12)
            ->get();

        if ($focusEventId && ! $events->contains('id', $focusEventId)) {
            $focus = $this->scholar->scopeEventsForUser(
                Event::query()
                    ->with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                    ->whereKey($focusEventId),
                $user
            )->first();

            if ($focus) {
                $events->push($focus);
            }
        }

        $attendances = $user->attendances()
            ->whereIn('event_id', $events->pluck('id')->all() ?: [0])
            ->get()
            ->keyBy('event_id');

        $formatted = $events->mapWithKeys(fn (Event $event) => [
            (string) $event->id => $this->formatSession(
                $event,
                $user,
                $event->registrations->first(),
                $attendances->get($event->id)
            ),
        ]);

        $sessions = $formatted
            ->filter(fn (array $session) => ! empty($session['status_visible']))
            ->sortBy(fn (array $session) => [$session['is_open'] ? 0 : 1, $session['ends_at'] ?? ''])
            ->take(12)
            ->values();

        $latestNotification = $user->scholarNotifications()
            ->where('category', 'attendance')
            ->latest('id')
            ->first(['id', 'title', 'body', 'event_id', 'created_at']);
        $unread = $user->unreadNotificationCount();

        return [
            'signature' => md5(json_encode([
                $sessions->pluck('signature')->all(),
                $latestNotification?->id,
                $unread,
            ])),
            'sessions' => $sessions->all(),
            'events' => $formatted->all(),
            'unread_notifications' => $unread,
            'latest_notification_id' => $latestNotification?->id ?? 0,
            'latest_notification' => $latestNotification ? [
                'id' => $latestNotification->id,
                'title' => $latestNotification->title,
                'body' => $latestNotification->body,
                'event_id' => $latestNotification->event_id,
                'created_at' => $latestNotification->created_at?->diffForHumans(),
            ] : null,
        ];
    }

    public function formatSession(
        Event $event,
        ?User $user = null,
        ?EventRegistration $registration = null,
        ?Attendance $attendance = null,
    ): array {
        $open = $event->isAttendanceOpen();
        $statusVisible = $open || $event->shouldShowAttendanceStatus();
        $label = $event->attendanceStatusLabel();
        $message = $this->sessionMessage($event);

        if ($user && ! $registration) {
            $registration = $event->relationLoaded('registrations')
                ? $event->registrations->firstWhere('user_id', $user->id)
                : $user->eventRegistrations()->where('event_id', $event->id)->first();
        }

        if ($user && ! $attendance) {
            $attendance = $user->attendances()->where('event_id', $event->id)->first();
        }

        if ($attendance && ! $attendance->relationLoaded('event')) {
            $attendance->setRelation('event', $event);
        }

        $canMark = $event->allowsScholarAttendanceActions()
            && $registration
            && in_array($registration->status, [
                EventRegistration::STATUS_CONFIRMED,
                EventRegistration::STATUS_FAILED_CHECK_IN,
            ], true);

        return [
            'event_id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'full_date' => $event->dateRangeLabel(),
            'time' => $event->scheduleTimeLabel(),
            'status' => $label,
            'is_open' => $open,
            // The published scholar script still reads these keys. They follow the staff session, not the event clock.
            'schedule_status' => $label,
            'schedule_open' => $open,
            'status_visible' => $statusVisible,
            'ends_at' => $event->ends_at?->toIso8601String(),
            'opened_at' => $event->attendanceOpenedAtLabel(),
            'closed_at' => $event->attendanceClosedAtLabel(),
            'can_check_in' => $canMark && ! $attendance?->hasCheckedIn(),
            'can_check_out' => $attendance?->scholarCanCheckOut() ?? false,
            'can_modify' => $attendance?->scholarCanModify() ?? false,
            'message' => $message,
            'schedule_message' => $message,
            'signature' => implode('|', [
                $event->id,
                $label,
                $statusVisible ? '1' : '0',
                $event->attendance_opened_at?->timestamp ?? 0,
                $event->attendance_closed_at?->timestamp ?? 0,
                $event->starts_at?->timestamp ?? 0,
                $event->ends_at?->timestamp ?? 0,
            ]),
        ];
    }

    public function sessionMessage(Event $event): string
    {
        $title = $event->title;

        if ($event->isAttendanceOpen()) {
            $opened = $event->attendanceOpenedAtLabel() ?? now()->format('M j, Y g:i A');

            return "Attendance for {$title} is OPEN as of {$opened}. You may mark your attendance until Scholar Staff closes the session.";
        }

        if ($event->attendanceMayBeFinalized() && $event->attendanceSessionClosed()) {
            $closed = $event->attendanceClosedAtLabel() ?? now()->format('M j, Y g:i A');

            return "Attendance for {$title} is CLOSED as of {$closed}.";
        }

        return "Attendance for {$title} is CLOSED. You can mark attendance only after Scholar Staff opens the session.";
    }

    public function scheduleStatusMessage(Event $event): string
    {
        $eventStamp = sprintf(
            '%s on %s at %s',
            $event->title,
            $event->starts_at?->format('M j, Y') ?? 'the scheduled date',
            $event->starts_at?->format('g:i A') ?? 'the scheduled time'
        );

        if ($event->isScheduleAttendanceOpen()) {
            $until = $event->ends_at?->format('g:i A') ?? 'the scheduled end time';

            return "Attendance for {$eventStamp} is OPEN until {$until}.";
        }

        $ended = $event->ends_at?->format('M j, Y g:i A') ?? 'the scheduled end time';

        return "Attendance for {$eventStamp} is CLOSED. The event ended at {$ended}.";
    }

    private function scholarAttendanceEventsQuery(User $user, $now)
    {
        return $this->scholar->scopeEventsForUser(
            Event::query()
                ->with(['registrations' => fn ($q) => $q->where('user_id', $user->id)])
                ->where(function ($query) use ($now) {
                    $query->where(function ($visible) use ($now) {
                        $visible->attendanceStatusVisible($now);
                    })->orWhere('attendance_is_open', true);
                }),
            $user
        );
    }

    private function notifyScholars(Event $event, string $action): void
    {
        if (! $event->scholarship_program_id) {
            return;
        }

        $opened = $action === AttendanceSessionLog::ACTION_OPENED;
        $actedAt = $opened
            ? ($event->attendanceOpenedAtLabel() ?? now()->format('M j, Y g:i A'))
            : ($event->attendanceClosedAtLabel() ?? now()->format('M j, Y g:i A'));

        $title = $opened ? 'Attendance Opened' : 'Attendance Closed';
        $body = $opened
            ? "Attendance for {$event->title} is now OPEN as of {$actedAt}. You can mark your attendance on the Events page until Scholar Staff closes the session."
            : "Attendance for {$event->title} is now CLOSED as of {$actedAt}.";

        $now = now();
        $rows = [];

        User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $event->scholarship_program_id)
            ->select(['id', 'notification_preferences'])
            ->each(function (User $scholar) use ($event, $title, $body, $now, &$rows) {
                $prefs = $scholar->notificationPreferences();
                if (! ($prefs['attendance_updates'] ?? true)) {
                    return;
                }

                $rows[] = [
                    'user_id' => $scholar->id,
                    'event_id' => $event->id,
                    'title' => $title,
                    'body' => $body,
                    'category' => 'attendance',
                    'is_important' => true,
                    'is_read' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        foreach (array_chunk($rows, 100) as $chunk) {
            ScholarNotification::insert($chunk);
        }

        if ($rows === []) {
            return;
        }

        User::query()
            ->whereIn('id', array_column($rows, 'user_id'))
            ->each(function (User $scholar) use ($title, $body, $event) {
                app(WorkflowMailService::class)->sendForNotification($scholar, $title, $body, 'attendance', $event->id);
            });
    }
}
