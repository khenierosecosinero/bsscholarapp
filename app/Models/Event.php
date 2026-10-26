<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScholarshipClub;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use BelongsToScholarshipClub;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'service_hours', 'organizer', 'image_url', 'image_path', 'status', 'scholarship_program_id',
        'scholarship_club_id',
        'attendance_is_open', 'attendance_opened_at', 'attendance_closed_at',
        'attendance_opened_by', 'attendance_closed_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'service_hours' => 'decimal:2',
            'attendance_is_open' => 'boolean',
            'attendance_opened_at' => 'datetime',
            'attendance_closed_at' => 'datetime',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Scholars whose attendance for this event is approved/verified.
     */
    public function scopeWithVerifiedParticipantCount(Builder $query): Builder
    {
        return $query->withCount([
            'attendances as verified_participant_count' => function ($attendance) {
                $attendance->where('status', Attendance::STATUS_APPROVED)
                    ->whereNotNull('check_in')
                    ->whereHas('user', fn ($user) => $user->where('role', User::ROLE_SCHOLAR));
            },
        ]);
    }

    public function verifiedParticipantCount(): int
    {
        if (array_key_exists('verified_participant_count', $this->attributes)) {
            return (int) $this->attributes['verified_participant_count'];
        }

        return (int) $this->attendances()
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->whereHas('user', fn ($user) => $user->where('role', User::ROLE_SCHOLAR))
            ->count();
    }

    public function directoryClubLabel(?int $selectedClubId = null): string
    {
        $clubs = $this->scholarshipProgram?->relationLoaded('clubs')
            ? $this->scholarshipProgram->clubs
            : $this->scholarshipProgram?->clubs()->available()->orderBy('name')->get();

        $clubs = collect($clubs)->reject(
            fn ($club) => $club instanceof ScholarshipClub && ScholarshipClub::isRetiredName($club->name)
        );

        if ($selectedClubId) {
            $selected = $clubs->firstWhere('id', $selectedClubId);
            if ($selected) {
                return $selected->name;
            }
        }

        $names = $clubs->pluck('name')->filter()->unique()->values();

        return $names->isEmpty() ? '—' : $names->implode(', ');
    }

    public function attendanceSessionLogs(): HasMany
    {
        return $this->hasMany(AttendanceSessionLog::class);
    }

    public function daySessions(): HasMany
    {
        return $this->hasMany(AttendanceDaySession::class);
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($this->hasStoredImage() && $this->id) {
                    return route('events.image', $this);
                }

                return $value;
            },
        );
    }

    public function hasStoredImage(): bool
    {
        return filled($this->image_path)
            && Storage::disk('public')->exists($this->image_path);
    }

    public function imageResponse()
    {
        if (! $this->hasStoredImage()) {
            abort(404, 'Event image not found.');
        }

        return Storage::disk('public')->response($this->image_path);
    }

    public function attendanceOpenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_opened_by');
    }

    public function attendanceClosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_closed_by');
    }

    public function isAttendanceOpen(): bool
    {
        return (bool) $this->attendance_is_open;
    }

    public function attendanceStatusVisibleUntil(): ?CarbonInterface
    {
        return $this->ends_at?->copy()->addHour();
    }

    public function shouldShowAttendanceStatus(?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        if (! $this->starts_at || ! $this->ends_at) {
            return false;
        }

        return $at->greaterThanOrEqualTo($this->starts_at)
            && $at->lessThanOrEqualTo($this->attendanceStatusVisibleUntil());
    }

    public function isScheduleAttendanceOpen(?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        if (! $this->starts_at || ! $this->ends_at) {
            return false;
        }

        return $at->between($this->starts_at, $this->ends_at);
    }

    public function scheduleAttendanceStatusLabel(?CarbonInterface $at = null): string
    {
        return $this->isScheduleAttendanceOpen($at) ? 'OPEN' : 'CLOSED';
    }

    public function scopeAttendanceStatusVisible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at->copy()->subHour());
    }

    public function attendanceSessionClosed(): bool
    {
        return ! $this->isAttendanceOpen()
            && $this->attendance_opened_at
            && $this->attendance_closed_at
            && $this->attendance_closed_at->gte($this->attendance_opened_at);
    }

    public function attendanceStatusLabel(): string
    {
        return $this->isAttendanceOpen() ? 'OPEN' : 'CLOSED';
    }

    public function attendanceStatusBadgeClass(): string
    {
        return $this->isAttendanceOpen() ? 'green' : 'gray';
    }

    public function attendanceOpenedAtLabel(): ?string
    {
        return $this->timestampLabel($this->attendance_opened_at);
    }

    public function attendanceClosedAtLabel(): ?string
    {
        return $this->timestampLabel($this->attendance_closed_at);
    }

    public function timestampLabel(?CarbonInterface $at): ?string
    {
        return $at?->copy()->timezone(config('app.timezone'))->format('F j, Y, g:i A');
    }

    /**
     * Single-day events show one date. Multi-day events show the full range.
     */
    public function dateRangeLabel(): string
    {
        $start = $this->zonedStart();
        if (! $start) {
            return 'Not scheduled';
        }

        $end = $this->zonedEnd();
        if (! $end || $end->isSameDay($start)) {
            return $start->format('F j, Y');
        }

        return $start->format('F j, Y').' – '.$end->format('F j, Y');
    }

    public function scheduleWhenLabel(): string
    {
        $start = $this->zonedStart();
        if (! $start) {
            return 'Not scheduled';
        }

        $end = $this->zonedEnd();
        if (! $end || $end->isSameDay($start)) {
            $time = $end
                ? $start->format('g:i A').' – '.$end->format('g:i A')
                : $start->format('g:i A');

            return $start->format('F j, Y').' · '.$time;
        }

        return $start->format('F j, Y, g:i A').' – '.$end->format('F j, Y, g:i A');
    }

    /**
     * One attendance session covers the whole event. Missed check-ins are final
     * after that session is closed, or after the event has ended.
     */
    public function attendanceMayBeFinalized(): bool
    {
        if ($this->isAttendanceOpen()) {
            return false;
        }

        return $this->attendanceSessionClosed() || $this->hasEnded();
    }

    public function isWithinAttendancePeriod(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $this->starts_at || ! $this->ends_at) {
            return false;
        }

        return $at->betweenIncluded($this->starts_at, $this->ends_at);
    }

    public function isHappeningNow(): bool
    {
        $now = now();

        return $now->between($this->starts_at, $this->ends_at);
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_ONGOING => 'Confirmed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_COMPLETED => 'Completed',
            default => ucfirst(str_replace('_', ' ', (string) ($this->status ?? 'upcoming'))),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'orange',
            self::STATUS_CONFIRMED, self::STATUS_ONGOING => 'green',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_COMPLETED => 'gray',
            default => 'gray',
        };
    }

    public function scheduleLabel(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'Cancelled';
        }

        if ($this->status === self::STATUS_PENDING) {
            return 'Pending';
        }

        if ($this->status === self::STATUS_COMPLETED) {
            return 'Completed';
        }

        if ($this->isHappeningNow() && $this->isCompletableStatus()) {
            return 'Ongoing';
        }

        if ($this->hasEnded() && $this->isCompletableStatus()) {
            return 'Completed';
        }

        return match ($this->status) {
            self::STATUS_CONFIRMED, self::STATUS_ONGOING => 'Confirmed',
            default => 'Upcoming',
        };
    }

    public function scheduleBadgeClass(): string
    {
        return match ($this->scheduleLabel()) {
            'Ongoing' => 'blue',
            'Completed' => 'gray',
            'Pending' => 'orange',
            'Confirmed' => 'green',
            'Cancelled' => 'red',
            default => 'green',
        };
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Scholars may register only for confirmed/ongoing events that are still open.
     */
    public function allowsScholarRegistration(): bool
    {
        if (! in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_ONGOING], true)) {
            return false;
        }

        return ! $this->hasEnded() || $this->isAttendanceOpen();
    }

    /**
     * Scholars may check in/out or upload attendance photos only while confirmed/ongoing
     * and the attendance session is open.
     */
    public function allowsScholarAttendanceActions(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_ONGOING], true)
            && $this->isAttendanceOpen()
            && $this->isWithinAttendancePeriod();
    }

    public function isCompletableStatus(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_ONGOING], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_ONGOING,
        ], true);
    }

    /**
     * Confirmed/Ongoing events may be moved back to Pending before they end.
     * Cancelled and Completed events cannot be changed this way.
     */
    public function canMarkPending(): bool
    {
        return $this->isCompletableStatus() && ! $this->hasEnded();
    }

    /**
     * Pending events may be Confirmed. Cancelled and Completed cannot.
     */
    public function canMarkConfirmed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Staff may change status via dropdown while the event is still Pending/Confirmed/Ongoing.
     */
    public function canEditStaffStatus(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_ONGOING,
        ], true);
    }

    /**
     * Dropdown value for staff status control (ongoing is shown as confirmed).
     */
    public function staffStatusSelectValue(): string
    {
        return match ($this->status) {
            self::STATUS_ONGOING => self::STATUS_CONFIRMED,
            self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED => $this->status,
            default => $this->status ?? self::STATUS_PENDING,
        };
    }

    /**
     * Apply a staff-selected status using existing transition rules.
     */
    public function applyStaffStatus(string $status): bool
    {
        $status = strtolower(trim($status));
        $current = $this->staffStatusSelectValue();

        if ($status === $current) {
            return true;
        }

        return match ($status) {
            self::STATUS_PENDING => $this->markPending(),
            self::STATUS_CONFIRMED => $this->markConfirmed(),
            self::STATUS_CANCELLED => $this->cancel(),
            default => false,
        };
    }

    public function markPending(): bool
    {
        if (! $this->canMarkPending()) {
            return false;
        }

        $updated = static::query()
            ->whereKey($this->id)
            ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_ONGOING])
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->update(['status' => self::STATUS_PENDING]);

        if ($updated > 0) {
            $this->status = self::STATUS_PENDING;
            $this->syncOriginalAttribute('status');

            return true;
        }

        return false;
    }

    public function markConfirmed(): bool
    {
        if (! $this->canMarkConfirmed()) {
            return false;
        }

        $updated = static::query()
            ->whereKey($this->id)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_CONFIRMED]);

        if ($updated > 0) {
            $this->status = self::STATUS_CONFIRMED;
            $this->syncOriginalAttribute('status');

            return true;
        }

        return false;
    }

    /**
     * Mark Confirmed/Ongoing events as Completed once ends_at is past (app timezone).
     * Never completes Pending or Cancelled events.
     */
    public function syncStatusFromSchedule(): self
    {
        if (! $this->hasEnded() || ! $this->isCompletableStatus()) {
            return $this;
        }

        $updated = static::query()
            ->whereKey($this->id)
            ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_ONGOING])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => self::STATUS_COMPLETED]);

        if ($updated > 0) {
            $this->status = self::STATUS_COMPLETED;
            $this->syncOriginalAttribute('status');
        }

        return $this;
    }

    /**
     * Batch-complete expired confirmed events (scheduler-safe, no double-writes).
     * Cancelled and pending events are never completed.
     *
     * @return list<int> IDs that transitioned to completed
     */
    public static function completeExpiredConfirmed(): int
    {
        return count(static::completeExpiredConfirmedIds());
    }

    /**
     * @return list<int>
     */
    public static function completeExpiredConfirmedIds(): array
    {
        $ids = static::query()
            ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_ONGOING])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        static::query()
            ->whereIn('id', $ids)
            ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_ONGOING])
            ->update(['status' => self::STATUS_COMPLETED]);

        return $ids;
    }

    public function cancel(): bool
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        $updated = static::query()
            ->whereKey($this->id)
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_CONFIRMED,
                self::STATUS_ONGOING,
            ])
            ->update(['status' => self::STATUS_CANCELLED]);

        if ($updated > 0) {
            $this->status = self::STATUS_CANCELLED;
            $this->syncOriginalAttribute('status');

            return true;
        }

        return false;
    }

    public function canBeRemoved(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Permanently delete a cancelled event only. Non-cancelled events are rejected.
     */
    public function removeCancelled(): bool
    {
        if (! $this->canBeRemoved()) {
            return false;
        }

        $imagePath = $this->image_path;

        $deleted = DB::transaction(function () {
            $stillCancelled = static::query()
                ->whereKey($this->id)
                ->where('status', self::STATUS_CANCELLED)
                ->exists();

            if (! $stillCancelled) {
                return false;
            }

            $this->releaseDependents();

            return static::query()
                ->whereKey($this->id)
                ->where('status', self::STATUS_CANCELLED)
                ->delete() > 0;
        });

        if ($deleted && filled($imagePath) && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        return $deleted;
    }

    /**
     * Detach rows that belong to this event id before the event row is removed.
     * Notifications are kept, with the event link cleared, so the message history remains.
     */
    public function releaseDependents(): void
    {
        ScholarNotification::query()
            ->where('event_id', $this->id)
            ->update(['event_id' => null]);

        $this->attendances()->get()->each(function (Attendance $attendance) {
            if (filled($attendance->photo_path) && Storage::disk('public')->exists($attendance->photo_path)) {
                Storage::disk('public')->delete($attendance->photo_path);
            }
        });

        $this->attendances()->delete();
        $this->registrations()->delete();
        $this->daySessions()->delete();
        $this->attendanceSessionLogs()->delete();
    }

    /**
     * Events whose saved start and end overlap the given window.
     */
    public function scopeOverlappingDates(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('starts_at', '<=', $end)
            ->where(function (Builder $scoped) use ($start) {
                $scoped->where('ends_at', '>=', $start)
                    ->orWhere(function (Builder $inner) use ($start) {
                        $inner->whereNull('ends_at')->where('starts_at', '>=', $start);
                    });
            });
    }

    /**
     * Events that have not passed their saved end time.
     */
    public function scopeNotEnded(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query->where(function (Builder $scoped) use ($at) {
            $scoped->where('ends_at', '>=', $at)
                ->orWhere(function (Builder $inner) use ($at) {
                    $inner->whereNull('ends_at')->where('starts_at', '>=', $at);
                });
        });
    }

    /**
     * Scholar visibility: show events that had not already ended before the scholar
     * became active. Creation time alone must not hide upcoming/ongoing events that
     * staff published shortly before a scholar was approved.
     *
     * Staff visibility: keep creation-time gating so staff manage events created
     * after their account became active.
     */
    public function scopeVisibleToAccount(Builder $query, ?User $user): Builder
    {
        $from = $user?->eventsVisibleFrom();
        if ($from === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if ($user?->isScholarStaff()) {
            return $query->where("{$table}.created_at", '>=', $from);
        }

        return $query->where(function (Builder $scoped) use ($from, $table) {
            $scoped->where("{$table}.ends_at", '>=', $from)
                ->orWhere(function (Builder $inner) use ($from, $table) {
                    $inner->whereNull("{$table}.ends_at")
                        ->where("{$table}.starts_at", '>=', $from);
                });
        });
    }

    /**
     * Whether this event remains visible to a scholar given their activation time.
     */
    public function isVisibleAfter(?\DateTimeInterface $from): bool
    {
        if ($from === null) {
            return true;
        }

        if ($this->ends_at) {
            return $this->ends_at->gte($from);
        }

        return $this->starts_at !== null && $this->starts_at->gte($from);
    }

    public function scopeVisibleToStaff(Builder $query, ?User $staff): Builder
    {
        $query->visibleToClubMember($staff);

        $from = $staff?->staffEventsVisibleFrom();
        if ($from === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where("{$table}.created_at", '>=', $from);
    }

    /**
     * New scholar calendars skip completed/past events even if created after
     * they became active. Existing scholars without events_visible_from keep
     * those later events on the calendar.
     */
    public function scopeUpcomingForFreshScholar(Builder $query, User $user): Builder
    {
        if (! $user->hasFreshEventCalendar()) {
            return $query;
        }

        // Date window only. Do not exclude cancelled/completed by status here —
        // scholars must still see Cancelled (and Completed when still in-window)
        // after staff/admin updates.
        return $query
            ->where(function (Builder $scoped) {
                $scoped->where('ends_at', '>=', now())
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('ends_at')->where('starts_at', '>=', now());
                    });
            });
    }

    /**
     * Y-m-d keys for each calendar day covered by the saved start and end.
     * An end time of exactly midnight belongs to the previous day.
     */
    public function calendarDateKeys(): array
    {
        $start = $this->zonedStart();
        if (! $start) {
            return [];
        }

        $end = $this->zonedEnd() ?? $start->copy();
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        if ($end->equalTo($last) && $last->gt($cursor)) {
            $last->subDay();
        }

        $days = [];
        while ($cursor->lte($last)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    public function scheduleDateLabel(): string
    {
        $start = $this->zonedStart();
        if (! $start) {
            return 'the scheduled date';
        }

        $label = $start->format('F j, Y').' ('.$start->format('l').')';
        $end = $this->zonedEnd();
        if (! $end || $end->isSameDay($start)) {
            return $label;
        }

        return $label.' – '.$end->format('F j, Y').' ('.$end->format('l').')';
    }

    public function scheduleTimeLabel(): string
    {
        $start = $this->zonedStart();
        if (! $start) {
            return 'the scheduled time';
        }

        $end = $this->zonedEnd();
        if (! $end) {
            return $start->format('g:i A');
        }

        if ($end->isSameDay($start)) {
            return $start->format('g:i A').' - '.$end->format('g:i A');
        }

        return $start->format('g:i A').' – '.$end->format('M j, Y g:i A');
    }

    public function calendarDayTimeLabel(string $dateKey): string
    {
        $start = $this->zonedStart();
        $end = $this->zonedEnd();
        if (! $start || ! $end) {
            return $start?->format('g:i A') ?? '';
        }

        $day = Carbon::parse($dateKey, config('app.timezone'))->startOfDay();
        $startsToday = $day->equalTo($start->copy()->startOfDay());
        $endsToday = $day->equalTo($end->copy()->startOfDay())
            || ($end->equalTo($end->copy()->startOfDay()) && $day->equalTo($end->copy()->subDay()->startOfDay()));

        if ($startsToday && $end->isSameDay($start)) {
            return $start->format('g:i A').' - '.$end->format('g:i A');
        }

        if ($startsToday) {
            return $start->format('g:i A');
        }

        if ($endsToday) {
            return 'Until '.$end->format('g:i A');
        }

        return 'Continues';
    }

    private function zonedStart(): ?CarbonInterface
    {
        return $this->starts_at?->copy()->timezone(config('app.timezone'));
    }

    private function zonedEnd(): ?CarbonInterface
    {
        return $this->ends_at?->copy()->timezone(config('app.timezone'));
    }
}
