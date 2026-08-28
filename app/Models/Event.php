<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;

class Event extends Model
{
    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'service_hours', 'organizer', 'image_url', 'status', 'scholarship_program_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'service_hours' => 'decimal:2',
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

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
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
