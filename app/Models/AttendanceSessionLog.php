<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSessionLog extends Model
{
    public const ACTION_OPENED = 'opened';

    public const ACTION_CLOSED = 'closed';

    protected $fillable = [
        'event_id', 'staff_id', 'action', 'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
