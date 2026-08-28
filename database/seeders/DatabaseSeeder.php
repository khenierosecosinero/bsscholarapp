<?php

namespace Database\Seeders;

use App\Models\AcademicSetting;
use App\Services\AcademicSettingsService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ScholarshipProgramSeeder::class);
        $this->call(AdminSeeder::class);

        $yearStart = (int) now()->year;
        AcademicSetting::query()->delete();
        AcademicSetting::create([
            'year_start' => $yearStart,
            'year_end' => $yearStart + 1,
            'semester' => '2nd Semester',
        ]);
        app(AcademicSettingsService::class)->clearCache();
    }
}
