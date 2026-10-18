<?php

namespace App\Models;

use App\Support\SurigaoLocations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class ScholarshipProgram extends Model
{
    public const PICKER_PROVINCES = [
        'Surigao del Norte',
        'Surigao del Sur',
    ];

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
     * Only municipalities/cities that belong to the selected province are listed.
     * The scholar's currently saved city is kept so existing records still display.
     *
     * @return list<string>
     */
    /**
     * Province names shown in location dropdowns.
     * Saved provinces outside Surigao del Norte and Surigao del Sur stay selectable
     * so existing records can still be displayed and saved.
     *
     * @return list<string>
     */
    public static function provinceOptions(?string $currentProvince = null): array
    {
        $options = self::PICKER_PROVINCES;

        if (filled($currentProvince) && static::canonicalPickerProvince($currentProvince) === null) {
            array_unshift($options, trim((string) $currentProvince));
        }

        return $options;
    }

    /**
     * Municipality and city names keyed by province for cascading location fields.
     *
     * @return array<string, list<string>>
     */
    public static function municipalitiesByProvince(?string $currentProvince = null, ?string $currentCity = null): array
    {
        $catalog = [];

        foreach (self::PICKER_PROVINCES as $province) {
            $includeCurrent = filled($currentProvince)
                && strcasecmp((string) $currentProvince, $province) === 0;

            $catalog[$province] = static::municipalityOptions(
                $province,
                $includeCurrent ? $currentCity : null
            );
        }

        if (filled($currentProvince) && static::canonicalPickerProvince($currentProvince) === null) {
            $catalog[trim((string) $currentProvince)] = static::municipalityOptions($currentProvince, $currentCity);
        }

        return $catalog;
    }

    public static function municipalityOptions(?string $provinceName, ?string $currentCity = null): array
    {
        $provinceName = static::canonicalPickerProvince($provinceName);
        if ($provinceName === null) {
            return filled($currentCity) ? [(string) $currentCity] : [];
        }

        $names = collect(SurigaoLocations::municipalities($provinceName))
            ->merge(
                static::active()->cities()
                    ->where('province_name', $provinceName)
                    ->orderBy('location_name')
                    ->pluck('location_name')
            )
            ->unique()
            ->filter()
            ->sort(SORT_NATURAL)
            ->values()
            ->all();

        if (filled($currentCity) && ! in_array($currentCity, $names, true)) {
            array_unshift($names, $currentCity);
        }

        return $names;
    }

    public static function isPickerProvince(?string $name): bool
    {
        if (! filled($name)) {
            return false;
        }

        foreach (self::PICKER_PROVINCES as $province) {
            if (strcasecmp($province, trim((string) $name)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Constrain a scholarship_programs query to active Municipality/City programs
     * in Surigao del Norte or Surigao del Sur.
     */
    public static function constrainActivePickerCities(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('location_type', 'city_municipality')
            ->whereIn('province_name', self::PICKER_PROVINCES);
    }

    /**
     * Provinces with nested municipalities/cities for registration pickers.
     * The Province list is always Surigao del Norte and Surigao del Sur.
     *
     * @return list<array<string, mixed>>
     */
    public static function locationTree(bool $includeClubs = true): array
    {
        $programs = static::active()
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'location_type', 'province_name', 'name', 'display_name']);

        $clubsByProgram = $includeClubs
            ? ScholarshipClub::query()
                ->available()
                ->orderBy('name')
                ->get(['id', 'name', 'scholarship_program_id'])
                ->groupBy('scholarship_program_id')
            : collect();

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

        $cityPayload = function (BaseCollection $cities) use ($clubPayload): array {
            return static::sortAlphabetically($cities)->map(fn (self $city) => [
                'id' => $city->id,
                'name' => $city->location_name,
                'label' => $city->programLabel(),
                'club_name' => $city->clubName(),
                'clubs' => $clubPayload($city->id),
            ])->values()->all();
        };

        $provincesByName = $programs
            ->where('location_type', 'province')
            ->filter(fn (self $program) => static::isPickerProvince($program->location_name))
            ->keyBy(fn (self $program) => static::canonicalPickerProvince($program->location_name));

        $citiesByProvince = collect(self::PICKER_PROVINCES)
            ->mapWithKeys(fn (string $name) => [$name => collect()]);

        foreach ($programs->where('location_type', 'city_municipality') as $city) {
            $provinceName = static::canonicalPickerProvince($city->province_name);
            if ($provinceName === null) {
                continue;
            }

            $citiesByProvince[$provinceName]->push($city);
        }

        $tree = [];

        foreach (self::PICKER_PROVINCES as $provinceName) {
            $province = $provincesByName->get($provinceName);
            $cities = $citiesByProvince->get($provinceName, collect());

            $tree[] = [
                'id' => $province?->id,
                'name' => $provinceName,
                'label' => $province?->programLabel() ?? $provinceName,
                'club_name' => $province?->clubName() ?? $provinceName,
                'clubs' => $clubPayload($province?->id),
                'cities' => $cityPayload($cities),
            ];
        }

        return $tree;
    }

    public static function canonicalPickerProvince(?string $name): ?string
    {
        if (! filled($name)) {
            return null;
        }

        foreach (self::PICKER_PROVINCES as $province) {
            if (strcasecmp($province, trim((string) $name)) === 0) {
                return $province;
            }
        }

        return null;
    }
}
