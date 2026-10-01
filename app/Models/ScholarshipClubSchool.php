<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ScholarshipClubSchool extends Model
{
    protected $fillable = [
        'name',
        'scholarship_club_id',
        'created_by',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(ScholarshipClub::class, 'scholarship_club_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scholars(): HasMany
    {
        return $this->hasMany(User::class, 'scholarship_club_school_id');
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    public static function createForClub(string $name, int $clubId, ?int $createdBy = null): self
    {
        $name = static::normalizeName($name);
        static::assertUniqueName($name, $clubId);

        return static::create([
            'name' => $name,
            'scholarship_club_id' => $clubId,
            'created_by' => $createdBy,
        ]);
    }

    public function rename(string $name): void
    {
        $name = static::normalizeName($name);
        static::assertUniqueName($name, (int) $this->scholarship_club_id, $this->id);

        $this->name = $name;
        $this->save();

        User::query()
            ->where('scholarship_club_school_id', $this->id)
            ->update(['school_university' => $name]);
    }

    private static function assertUniqueName(string $name, int $clubId, ?int $ignoreId = null): void
    {
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Please enter a School/University name.',
            ]);
        }

        $exists = static::query()
            ->where('scholarship_club_id', $clubId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'That School/University is already added to this Scholarship Club.',
            ]);
        }
    }
}
