<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attendance extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED_CHECK_IN = 'failed_to_check_in';

    protected $fillable = [
        'user_id', 'event_id', 'academic_year_start', 'academic_year_end', 'semester',
        'check_in', 'check_out', 'hours_earned', 'status', 'remarks',
        'photo_path', 'photo_original_name', 'photo_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'hours_earned' => 'decimal:2',
            'photo_uploaded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hasCheckedIn(): bool
    {
        return $this->check_in !== null;
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path)
            && Storage::disk('public')->exists($this->photo_path);
    }

    public function canReplacePhoto(): bool
    {
        return $this->hasCheckedIn()
            && ! in_array($this->status, [self::STATUS_APPROVED, self::STATUS_FAILED_CHECK_IN], true);
    }

    public function isReadyForVerification(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->hasCheckedIn()
            && $this->check_out
            && $this->hasPhoto();
    }

    public function photoResponse()
    {
        if (! $this->hasPhoto() || ! Storage::disk('public')->exists($this->photo_path)) {
            abort(404, 'Attendance photo not found.');
        }

        return Storage::disk('public')->response(
            $this->photo_path,
            $this->photo_original_name ?? 'attendance-photo.jpg'
        );
    }

    public function creditsHours(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->hasCheckedIn()
            && (float) $this->hours_earned > 0;
    }

    public function recordedHours(): float
    {
        if (in_array($this->status, [self::STATUS_FAILED_CHECK_IN, self::STATUS_REJECTED], true)) {
            return 0.0;
        }

        return (float) ($this->hours_earned ?? 0);
    }

    public function hoursLabel(): string
    {
        return number_format($this->recordedHours(), 2).' hrs';
    }

    public function reviewNote(): string
    {
        if (filled($this->remarks)) {
            return $this->remarks;
        }

        return match ($this->status) {
            self::STATUS_APPROVED => 'Verified by Scholar Staff. Service hours credited.',
            self::STATUS_REJECTED => 'Not verified. No service hours credited.',
            self::STATUS_FAILED_CHECK_IN => 'Did not check in. No service hours credited.',
            self::STATUS_PENDING => $this->isReadyForVerification()
                ? 'Awaiting Scholar Staff verification. Hours are not credited yet.'
                : ($this->hasPhoto()
                    ? 'Check out to complete this attendance record.'
                    : 'Attach a participation photo so Scholar Staff can verify your hours.'),
            default => '—',
        };
    }

    public function statusLabel(): string
    {
        return self::labelFor($this->status);
    }

    public static function labelFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_FAILED_CHECK_IN => 'Failed to Check In',
            self::STATUS_PENDING => 'Pending',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Pending',
        };
    }
}
