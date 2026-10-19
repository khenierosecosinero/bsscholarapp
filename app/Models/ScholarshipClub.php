<?php

namespace App\Models;

use App\Support\LocationAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ScholarshipClub extends Model
{
    public const DUPLICATE_NAME_MESSAGE = 'This Scholarship Club name is already registered. Please use a different name.';

    protected $fillable = [
        'name',
        'name_key',
        'province',
        'city',
        'street',
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

    protected static function booted(): void
    {
        static::saving(function (self $club): void {
            if ($club->isDirty('name') || blank($club->name_key)) {
                $club->name_key = static::nameKey((string) $club->name);
            }
        });
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
     * Historical names removed by a one-time migration. Empty so any unique club name may be registered.
     *
     * @return list<string>
     */
    public static function retiredNames(): array
    {
        return [];
    }

    public static function isRetiredName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        return in_array(mb_strtolower(static::normalizeName($name)), static::retiredNames(), true);
    }

    /**
     * Reject names still owned by an existing Scholar Staff account (case-insensitive, trimmed).
     * Orphaned club rows (no staff owner) are purged so the name can be reused.
     */
    public static function assertNameIsUnique(string $name, ?int $exceptId = null): void
    {
        $key = static::nameKey($name);

        if ($key === '') {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'Please enter a Scholarship Club Name.',
            ]);
        }

        static::purgeOrphanedClubs($key, $exceptId);

        if (static::nameIsOwnedByStaff($key, $exceptId)) {
            throw ValidationException::withMessages([
                'scholarship_club_name' => self::DUPLICATE_NAME_MESSAGE,
            ]);
        }
    }

    /**
     * True when an existing Scholar Staff account still owns a club with this name key.
     */
    public static function nameIsOwnedByStaff(string $nameKey, ?int $exceptId = null): bool
    {
        $query = static::query()
            ->where('name_key', $nameKey)
            ->whereHas('members', function (Builder $members) {
                $members->where('role', User::ROLE_SCHOLAR_STAFF);
            });

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    /**
     * Remove club rows that no longer have any Scholar Staff owner so their names can be reused.
     * Scholar accounts are preserved and only unlinked.
     */
    public static function purgeOrphanedClubs(?string $nameKey = null, ?int $exceptId = null): int
    {
        $query = static::query()
            ->whereDoesntHave('members', function (Builder $members) {
                $members->where('role', User::ROLE_SCHOLAR_STAFF);
            });

        if ($nameKey !== null) {
            $query->where('name_key', $nameKey);
        }

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        $removed = 0;

        foreach ($query->orderBy('id')->get() as $club) {
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

    public function scopeNotRetired(Builder $query): Builder
    {
        foreach (static::retiredNames() as $name) {
            $query->whereRaw('LOWER(TRIM(name)) != ?', [$name]);
        }

        return $query;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active();
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
     * Free a club name after its owning Scholar Staff account is permanently deleted.
     * Keeps the club (and name) if another Scholar Staff account still owns it.
     * Scholar user rows are preserved; they are only unlinked from the released club.
     */
    public static function releaseAfterStaffPermanentDeletion(?int $clubId, int $deletedStaffId): bool
    {
        if ($clubId) {
            $club = static::query()->find($clubId);

            if ($club) {
                $otherStaffRemain = User::query()
                    ->where('role', User::ROLE_SCHOLAR_STAFF)
                    ->where('scholarship_club_id', $clubId)
                    ->whereKeyNot($deletedStaffId)
                    ->exists();

                if (! $otherStaffRemain) {
                    User::query()
                        ->where('scholarship_club_id', $clubId)
                        ->update([
                            'scholarship_club_id' => null,
                            'scholarship_club_school_id' => null,
                        ]);

                    $club->schools()->delete();
                    $club->delete();

                    return true;
                }
            }
        }

        // Catch clubs left behind when staff was removed without a club_id link.
        return static::purgeOrphanedClubs() > 0;
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
        return LocationAddress::line($this->street, $this->city, $this->province);
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
                'street' => $club->street,
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

    public static function nameKey(string $name): string
    {
        return mb_strtolower(static::normalizeName($name));
    }

    public static function createForProgram(string $name, int $programId, ?int $createdBy = null, ?string $street = null): self
    {
        $name = static::normalizeName($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'Please enter a Scholarship Club Name.',
            ]);
        }

        static::assertNameIsUnique($name);

        $program = ScholarshipProgram::query()->active()->find($programId);

        if (! $program || ! $program->isCityOrMunicipality()) {
            throw ValidationException::withMessages([
                'scholarship_program_id' => 'Please select the Municipality or City where the Scholarship Club is located.',
            ]);
        }

        $location = $program->registrationLocation();

        try {
            return static::create([
                'name' => $name,
                'name_key' => static::nameKey($name),
                'city' => $location['city'],
                'province' => $location['province'],
                'street' => LocationAddress::normalizeStreet($street),
                'scholarship_program_id' => $program->id,
                'created_by' => $createdBy,
                'is_active' => true,
            ]);
        } catch (QueryException $exception) {
            if (static::isUniqueNameConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'scholarship_club_name' => self::DUPLICATE_NAME_MESSAGE,
                ]);
            }

            throw $exception;
        }
    }

    public function rename(string $name): void
    {
        $this->updateDetails($name, (int) $this->scholarship_program_id);
    }

    public function updateDetails(string $name, int $programId, ?string $street = null, bool $syncStreet = false): void
    {
        $name = static::normalizeName($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'scholarship_club_name' => 'Please enter a Scholarship Club Name.',
            ]);
        }

        static::assertNameIsUnique($name, (int) $this->id);

        $program = ScholarshipProgram::query()->active()->find($programId);

        if (! $program || ! $program->isCityOrMunicipality()) {
            throw ValidationException::withMessages([
                'scholarship_program_id' => 'Please select a valid Municipality or City for the Scholarship Club address.',
            ]);
        }

        $location = $program->registrationLocation();

        $this->name = $name;
        $this->name_key = static::nameKey($name);
        $this->city = $location['city'];
        $this->province = $location['province'];
        if ($syncStreet) {
            $this->street = LocationAddress::normalizeStreet($street);
        }
        $this->scholarship_program_id = $program->id;

        try {
            $this->save();
        } catch (QueryException $exception) {
            if (static::isUniqueNameConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'scholarship_club_name' => self::DUPLICATE_NAME_MESSAGE,
                ]);
            }

            throw $exception;
        }

        User::query()
            ->where('scholarship_club_id', $this->id)
            ->update([
                'scholarship_program_id' => $program->id,
            ]);
    }

    private static function isUniqueNameConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'scholarship_clubs_name_key_unique')
            || (str_contains($message, 'unique') && str_contains($message, 'name_key'));
    }
}
