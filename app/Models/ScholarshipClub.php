<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ScholarshipClub extends Model
{
    protected $fillable = [
        'name',
        'province',
        'city',
        'scholarship_program_id',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(ScholarshipClubSchool::class)->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Names retired from the system. They must not appear as selectable Scholarship Clubs.
     *
     * @return list<string>
     */
    public static function retiredNames(): array
    {
        return [
            'batang surigaonon scholars club',
            "batang surigaonon scholar's club",
            'batang surigaonon scholar club',
        ];
    }

    public static function isRetiredName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        return in_array(mb_strtolower(static::normalizeName($name)), static::retiredNames(), true);
    }

    public static function assertNameIsAvailable(string $name): void
    {
        if (static::isRetiredName($name)) {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'That Scholarship Club is no longer available.',
            ]);
        }
    }

    public function scopeNotRetired(Builder $query): Builder
    {
        foreach (static::retiredNames() as $name) {
            $query->whereRaw('LOWER(TRIM(name)) != ?', [$name]);
        }

        return $query;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->notRetired();
    }

    public static function selectableId(?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return static::query()->available()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * Delete retired Scholarship Club rows and unlink members. Other clubs are left unchanged.
     */
    public static function removeRetiredClubs(): int
    {
        $removed = 0;

        foreach (static::query()->orderBy('id')->get() as $club) {
            if (! static::isRetiredName($club->name)) {
                continue;
            }

            User::query()
                ->where('scholarship_club_id', $club->id)
                ->update([
                    'scholarship_club_id' => null,
                    'scholarship_club_school_id' => null,
                ]);

            $club->schools()->delete();
            $club->delete();
            $removed++;
        }

        return $removed;
    }

    /**
     * Clubs registered at a province and optional municipality/city.
     * Does not create, update, or delete club records.
     */
    public function scopeForLocation(Builder $query, ?string $province, ?string $city = null): Builder
    {
        if (filled($province)) {
            $query->where('province', $province);
        }

        if (filled($city)) {
            $query->where('city', $city);
        }

        return $query;
    }

    public function addressLine(): string
    {
        if ($this->city && $this->province) {
            return $this->city.', '.$this->province;
        }

        return $this->city ?: $this->province ?: '';
    }

    /**
     * @return list<array{id: int, name: string, city: ?string, province: ?string}>
     */
    public static function registrationOptions(): array
    {
        return static::query()
            ->available()
            ->with(['schools' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->orderBy('city')
            ->get()
            ->map(fn (self $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'city' => $club->city,
                'province' => $club->province,
                'schools' => $club->schools
                    ->map(fn (ScholarshipClubSchool $school) => [
                        'id' => $school->id,
                        'name' => $school->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    public static function createForProgram(string $name, int $programId, ?int $createdBy = null): self
    {
        $name = static::normalizeName($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'Please enter a Scholarship Club Name.',
            ]);
        }

        static::assertNameIsAvailable($name);

        $program = ScholarshipProgram::query()->active()->find($programId);

        if (! $program || ! $program->isCityOrMunicipality()) {
            throw ValidationException::withMessages([
                'scholarship_program_id' => 'Please select the Municipality or City where the Scholarship Club is located.',
            ]);
        }

        $location = $program->registrationLocation();

        $exists = static::query()
            ->where('scholarship_program_id', $programId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'That Scholarship Club Name is already used in this area.',
            ]);
        }

        return static::create([
            'name' => $name,
            'city' => $location['city'],
            'province' => $location['province'],
            'scholarship_program_id' => $program->id,
            'created_by' => $createdBy,
            'is_active' => true,
        ]);
    }

    public function rename(string $name): void
    {
        $this->updateDetails($name, (int) $this->scholarship_program_id);
    }

    public function updateDetails(string $name, int $programId): void
    {
        $name = static::normalizeName($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'Please enter a Scholarship Club Name.',
            ]);
        }

        static::assertNameIsAvailable($name);

        $program = ScholarshipProgram::query()->active()->find($programId);

        if (! $program || ! $program->isCityOrMunicipality()) {
            throw ValidationException::withMessages([
                'scholarship_program_id' => 'Please select a valid Municipality or City for the Scholarship Club address.',
            ]);
        }

        $exists = static::query()
            ->where('scholarship_program_id', $program->id)
            ->whereKeyNot($this->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'That Scholarship Club Name is already used in this area.',
            ]);
        }

        $location = $program->registrationLocation();

        $this->name = $name;
        $this->city = $location['city'];
        $this->province = $location['province'];
        $this->scholarship_program_id = $program->id;
        $this->save();

        User::query()
            ->where('scholarship_club_id', $this->id)
            ->update([
                'scholarship_program_id' => $program->id,
            ]);
    }
}
