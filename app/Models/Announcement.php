<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'published_at', 'scholarship_program_id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ScholarNotification::class);
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        });
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
