<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class ScholarshipProgram extends Model
{
    protected $fillable = [
        'psgc_code',
        'location_name',
        'location_type',
        'name',
        'display_name',
        'province_name',
        'region_name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scholars(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_SCHOLAR);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->where('status', User::STATUS_APPROVED);
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(ScholarshipClub::class);
    }

    public function pendingStaff(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->where('status', User::STATUS_PENDING);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCities(Builder $query): Builder
    {
        return $query->where('location_type', 'city_municipality');
    }

    public function scopeProvinces(Builder $query): Builder
    {
        return $query->where('location_type', 'province');
    }

    public function isCityProgram(): bool
    {
        return $this->isCityOrMunicipality();
    }

    public function isProvinceProgram(): bool
    {
        return $this->isProvince();
    }

    public static function slugForLocation(string $locationName): string
    {
        return Str::slug($locationName);
    }

    public function dropdownLabel(): string
    {
        return $this->programLabel();
    }

    public function programTypeLabel(): string
    {
        return match ($this->location_type) {
            'province' => 'Province Scholar Program',
            'city_municipality' => 'City Scholar Program',
            default => 'Scholar Program',
        };
    }

    public function programLabel(): string
    {
        $name = $this->display_name ?: $this->location_name;

        return "{$name} — {$this->programTypeLabel()}";
    }

    public function clubName(): string
    {
        return $this->display_name ?: $this->name ?: $this->location_name;
    }

    public function clubTypeLabel(): string
    {
        return 'Scholarship Club';
    }

    /**
     * Derive saved city/province values from this program for user records.
     *
     * @return array{city: ?string, province: string}
     */
    public function registrationLocation(): array
    {
        return [
            'city' => $this->isCityOrMunicipality() ? $this->location_name : null,
            'province' => $this->isProvince()
                ? $this->location_name
                : ($this->province_name ?: ''),
        ];
    }

    /**
     * Sort programs alphabetically by their full program label.
     */
    public static function sortAlphabetically(BaseCollection $programs): BaseCollection
    {
        return $programs
            ->sortBy(fn (self $program) => Str::lower($program->programLabel()), SORT_NATURAL)
            ->values();
    }

    /**
     * Active programs for pickers, sorted alphabetically.
     */
    public static function activeForPicker(bool $citiesOnly = false): Collection
    {
        $query = static::active();

        if ($citiesOnly) {
            $query->where('location_type', 'city_municipality');
        }

        return static::sortAlphabetically($query->get());
    }

    /**
     * Active programs grouped by program type for selection menus.
     *
     * @return array{cities: Collection, provinces: Collection}
     */
    public static function groupedActiveForPicker(bool $citiesOnly = false): array
    {
        $programs = static::activeForPicker($citiesOnly);

        if ($citiesOnly) {
            return [
                'cities' => $programs,
                'provinces' => collect(),
            ];
        }

        return [
            'cities' => static::sortAlphabetically(
                $programs->where('location_type', 'city_municipality')
            ),
            'provinces' => static::sortAlphabetically(
                $programs->where('location_type', 'province')
            ),
        ];
    }

    public function isProvince(): bool
    {
        return $this->location_type === 'province';
    }

    public function isCityOrMunicipality(): bool
    {
        return $this->location_type === 'city_municipality';
    }

    /**
     * Scholarship program IDs used for data scoping.
     * Each city/municipality and province program is kept separate.
     */
    public function coveredLocationIds(): array
    {
        return [$this->id];
    }

    /**
     * Content a scholar in this program should see: only their assigned program.
     */
    public function visibleLocationIds(): array
    {
        return [$this->id];
    }

    /**
     * City and municipality names for profile pickers.
     * Scoped to the scholar's province when that province has city records.
     *
     * @return list<string>
     */
    public static function municipalityOptions(?string $provinceName, ?string $currentCity = null): array
    {
        $query = static::active()->cities();

        if (filled($provinceName)) {
            $query->where('province_name', $provinceName);
        }

        $names = $query
            ->orderBy('location_name')
            ->pluck('location_name')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($names === [] && filled($provinceName)) {
            return static::municipalityOptions(null, $currentCity);
        }

        if (filled($currentCity) && ! in_array($currentCity, $names, true)) {
            array_unshift($names, $currentCity);
        }

        return $names;
    }

    /**
     * Provinces with nested municipalities/cities for registration pickers.
     */
    public static function locationTree(): array
    {
        $programs = static::active()
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'location_type', 'province_name', 'region_name', 'name', 'display_name']);

        $clubsByProgram = ScholarshipClub::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'scholarship_program_id'])
            ->groupBy('scholarship_program_id');

        $clubPayload = function (?int $programId) use ($clubsByProgram): array {
            if (! $programId) {
                return [];
            }

            return ($clubsByProgram->get($programId) ?? collect())
                ->map(fn (ScholarshipClub $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'program_id' => $club->scholarship_program_id,
                ])
                ->values()
                ->all();
        };

        $provinces = $programs->where('location_type', 'province')->values();
        $citiesByProvince = $programs
            ->where('location_type', 'city_municipality')
            ->groupBy(fn (self $program) => $program->province_name ?: 'Other');

        $tree = [];
        $listedProvinces = [];

        foreach ($provinces as $province) {
            $listedProvinces[] = $province->location_name;
            $cities = $citiesByProvince->get($province->location_name, collect());

            $tree[] = [
                'id' => $province->id,
                'name' => $province->location_name,
                'label' => $province->programLabel(),
                'club_name' => $province->clubName(),
                'clubs' => $clubPayload($province->id),
                'region' => $province->region_name,
                'cities' => static::sortAlphabetically($cities)->map(fn (self $city) => [
                    'id' => $city->id,
                    'name' => $city->location_name,
                    'label' => $city->programLabel(),
                    'club_name' => $city->clubName(),
                    'clubs' => $clubPayload($city->id),
                ])->values()->all(),
            ];
        }

        foreach ($citiesByProvince as $provinceName => $cities) {
            if (in_array($provinceName, $listedProvinces, true)) {
                continue;
            }

            $tree[] = [
                'id' => null,
                'name' => $provinceName,
                'label' => $provinceName,
                'club_name' => $provinceName,
                'clubs' => [],
                'region' => $cities->first()?->region_name,
                'cities' => static::sortAlphabetically($cities)->map(fn (self $city) => [
                    'id' => $city->id,
                    'name' => $city->location_name,
                    'label' => $city->programLabel(),
                    'club_name' => $city->clubName(),
                    'clubs' => $clubPayload($city->id),
                ])->values()->all(),
            ];
        }

        usort($tree, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $tree;
    }
}
