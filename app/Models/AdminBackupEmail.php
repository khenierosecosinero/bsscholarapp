<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminBackupEmail extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'verified_at',
        'verify_token',
        'verify_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verify_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function normalizedEmail(): string
    {
        return strtolower(trim((string) $this->email));
    }
}
