<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveConnection extends Model
{
    protected $fillable = [
        'token',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted:array',
        ];
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
