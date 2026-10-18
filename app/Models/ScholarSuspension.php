<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ScholarSuspension extends Model
{
    public const LIFT_AUTOMATIC = 'automatic';

    public const LIFT_MANUAL = 'manual';

    public const LIFT_BLOCKED = 'blocked';

    protected $fillable = [
        'user_id',
        'issued_by',
        'reason',
        'starts_at',
        'ends_at',
        'lifted_at',
        'lift_type',
        'lifted_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function scholar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isOpen(): bool
    {
        return $this->lifted_at === null;
    }

    public function remainingLabel(?Carbon $at = null): string
    {
        $at = $at ?? now();
        $ends = $this->ends_at;

        if ($ends === null || $at->greaterThanOrEqualTo($ends)) {
            return 'The suspension period has ended.';
        }

        $interval = $at->diff($ends);
        $days = (int) $interval->days;
        $hours = (int) $interval->h;
        $minutes = (int) $interval->i;

        if ($days > 0) {
            $label = $days.' '.($days === 1 ? 'day' : 'days');
            if ($hours > 0) {
                $label .= ' and '.$hours.' '.($hours === 1 ? 'hour' : 'hours');
            }

            return $label.' remaining.';
        }

        if ($hours > 0) {
            $label = $hours.' '.($hours === 1 ? 'hour' : 'hours');
            if ($minutes > 0) {
                $label .= ' and '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
            }

            return $label.' remaining.';
        }

        if ($minutes > 0) {
            return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes').' remaining.';
        }

        return 'Less than a minute remaining.';
    }
}
