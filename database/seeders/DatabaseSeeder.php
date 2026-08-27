<?php

namespace Database\Seeders;

use App\Models\AcademicSetting;
use App\Models\DocumentType;
use App\Services\AcademicSettingsService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ScholarshipProgramSeeder::class);

        $yearStart = (int) now()->year;
        AcademicSetting::query()->delete();
        AcademicSetting::create([
            'year_start' => $yearStart,
            'year_end' => $yearStart + 1,
            'semester' => '2nd Semester',
        ]);
        app(AcademicSettingsService::class)->clearCache();

        $docTypes = [
            ['name' => 'Birth Certificate', 'slug' => 'birth-certificate', 'description' => 'Official birth certificate from PSA'],
            ['name' => 'Student ID', 'slug' => 'student-id', 'description' => 'Valid school identification card'],
            ['name' => 'Latest Grades', 'slug' => 'latest-grades', 'description' => 'Most recent grade report'],
            ['name' => 'Certificate of Registration', 'slug' => 'certificate-of-registration', 'description' => 'Current semester COR'],
            ['name' => 'Good Moral Certificate', 'slug' => 'good-moral-certificate', 'description' => 'Certificate of good moral character'],
        ];

        foreach ($docTypes as $type) {
            DocumentType::updateOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
