<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'service_hours', 'organizer', 'image_url', 'image_path', 'status', 'scholarship_program_id',
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
            : $this->scholarshipProgram?->clubs()->active()->orderBy('name')->get();

        $clubs = collect($clubs);

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
        return $this->attendance_opened_at?->format('M j, Y g:i A');
    }

    public function attendanceClosedAtLabel(): ?string
    {
        return $this->attendance_closed_at?->format('M j, Y g:i A');
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

    public function scheduleLabel(): string
    {
        if ($this->isHappeningNow()) {
            return 'Ongoing';
        }

        if ($this->hasEnded()) {
            return 'Completed';
        }

        return match ($this->status) {
            'pending' => 'Pending',
            'completed' => 'Completed',
            'ongoing' => 'Ongoing',
            'confirmed' => 'Confirmed',
            default => 'Upcoming',
        };
    }

    public function scheduleBadgeClass(): string
    {
        return match ($this->scheduleLabel()) {
            'Ongoing' => 'blue',
            'Completed' => 'gray',
            'Pending' => 'orange',
            'Confirmed' => 'blue',
            default => 'green',
        };
    }

    public function syncStatusFromSchedule(): self
    {
        if ($this->hasEnded() && $this->status !== 'completed') {
            $this->updateQuietly(['status' => 'completed']);
        } elseif ($this->isHappeningNow() && $this->status !== 'ongoing') {
            $this->updateQuietly(['status' => 'ongoing']);
        }

        return $this;
    }

    /**
     * Events whose schedule overlaps the given window, so staff-set dates
     * appear on every calendar day they occupy.
     */
    public function scopeOverlappingDates(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('starts_at', '<=', $end)
            ->where('ends_at', '>=', $start);
    }

    /**
     * Scholar and staff only see events created after they became active
     * (events_visible_from, or account created_at). Older program events stay
     * in the database for other accounts, reports, and admin.
     */
    public function scopeVisibleToAccount(Builder $query, ?User $user): Builder
    {
        $from = $user?->eventsVisibleFrom();
        if ($from === null) {
            return $query;
        }

        return $query->where($query->getModel()->getQualifiedCreatedAtColumn(), '>=', $from);
    }

    public function scopeVisibleToStaff(Builder $query, ?User $staff): Builder
    {
        return $query->visibleToAccount($staff);
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

        return $query
            ->where(function (Builder $scoped) {
                $scoped->where('ends_at', '>=', now())
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('ends_at')->where('starts_at', '>=', now());
                    });
            })
            ->where(function (Builder $scoped) {
                $scoped->whereNull('status')->orWhere('status', '!=', 'completed');
            });
    }

    /**
     * Y-m-d keys for each calendar day this event should appear on.
     */
    public function calendarDateKeys(): array
    {
        if (! $this->starts_at) {
            return [];
        }

        $days = [];
        $cursor = $this->starts_at->copy()->startOfDay();
        $last = ($this->ends_at ?? $this->starts_at)->copy()->startOfDay();

        if ($last->lt($cursor)) {
            $last = $cursor->copy();
        }

        while ($cursor->lte($last)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }
}
