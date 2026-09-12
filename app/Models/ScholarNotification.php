<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarNotification extends Model
{
    protected $table = 'scholar_notifications';

    protected $fillable = [
        'user_id', 'announcement_id', 'event_id', 'title', 'body', 'category',
        'is_read', 'is_important',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_important' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
