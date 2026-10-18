<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_programs')) {
            return;
        }

        $catalog = require database_path('data/surigao-locations.php');
        $now = now();

        foreach ($catalog['provinces'] as $provinceName => $province) {
            $this->insertMissing([
                'psgc_code' => $province['code'],
                'location_name' => $provinceName,
                'location_type' => 'province',
                'name' => $provinceName.' Scholarship Program',
                'display_name' => $provinceName.' (Province)',
                'province_name' => null,
                'slug' => $province['slug'] !== '' ? $province['slug'] : str($provinceName)->slug()->toString(),
            ], $now);

            foreach ($province['municipalities'] as $municipality) {
                $this->insertMissing([
                    'psgc_code' => $municipality['code'],
                    'location_name' => $municipality['name'],
                    'location_type' => 'city_municipality',
                    'name' => $municipality['name'].' Scholarship Program',
                    'display_name' => $municipality['name'].', '.$provinceName,
                    'province_name' => $provinceName,
                    'slug' => $municipality['slug'] !== '' ? $municipality['slug'] : str($municipality['name'].'-'.$provinceName)->slug()->toString(),
                ], $now);
            }
        }
    }

    public function down(): void
    {
        // Keep inserted locations. Removing them would detach existing accounts.
    }

    /**
     * @param  array{psgc_code: string, location_name: string, location_type: string, name: string, display_name: string, province_name: ?string, slug: string}  $row
     */
    private function insertMissing(array $row, mixed $now): void
    {
        $exists = DB::table('scholarship_programs')->where(function ($query) use ($row) {
            $query->where('psgc_code', $row['psgc_code'])
                ->orWhere(function ($match) use ($row) {
                    $match->where('location_name', $row['location_name'])
                        ->where('location_type', $row['location_type']);

                    if ($row['province_name']) {
                        $match->where('province_name', $row['province_name']);
                    }
                });
        })->exists();

        if ($exists) {
            return;
        }

        $slug = $row['slug'];
        if (DB::table('scholarship_programs')->where('slug', $slug)->exists()) {
            $slug .= '-'.$row['psgc_code'];
        }

        DB::table('scholarship_programs')->insert([
            'psgc_code' => $row['psgc_code'],
            'location_name' => $row['location_name'],
            'location_type' => $row['location_type'],
            'name' => $row['name'],
            'display_name' => $row['display_name'],
            'province_name' => $row['province_name'],
            'slug' => $slug,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
