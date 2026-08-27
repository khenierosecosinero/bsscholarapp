<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->hasMany(User::class)->where('role', User::ROLE_SCHOLAR_STAFF);
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

    public static function slugForLocation(string $locationName): string
    {
        return Str::slug($locationName);
    }

    public function dropdownLabel(): string
    {
        return $this->display_name ?: $this->location_name;
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
     * Location IDs covered by this program: a city is itself;
     * a province includes itself and every municipality/city under it.
     */
    public function coveredLocationIds(): array
    {
        if ($this->isProvince()) {
            return static::query()
                ->where(function (Builder $query) {
                    $query->whereKey($this->id)
                        ->orWhere(function (Builder $cities) {
                            $cities->where('location_type', 'city_municipality')
                                ->where('province_name', $this->location_name);
                        });
                })
                ->pluck('id')
                ->all();
        }

        return [$this->id];
    }

    /**
     * Content a scholar in this location should see: their own program,
     * plus the parent province when they registered under a city/municipality.
     */
    public function visibleLocationIds(): array
    {
        $ids = [$this->id];

        if ($this->isCityOrMunicipality() && $this->province_name) {
            $provinceId = static::query()
                ->where('location_type', 'province')
                ->where('location_name', $this->province_name)
                ->value('id');

            if ($provinceId) {
                $ids[] = (int) $provinceId;
            }
        }

        return $ids;
    }

    /**
     * Provinces with nested municipalities/cities for registration pickers.
     */
    public static function locationTree(): array
    {
        $programs = static::active()
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'location_type', 'province_name', 'region_name']);

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
                'region' => $province->region_name,
                'cities' => $cities->map(fn (self $city) => [
                    'id' => $city->id,
                    'name' => $city->location_name,
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
                'region' => $cities->first()?->region_name,
                'cities' => $cities->map(fn (self $city) => [
                    'id' => $city->id,
                    'name' => $city->location_name,
                ])->values()->all(),
            ];
        }

        usort($tree, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $tree;
    }
}
