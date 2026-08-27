<?php

namespace Database\Seeders;

use App\Services\ScholarshipProgramImportService;
use Illuminate\Database\Seeder;

class ScholarshipProgramSeeder extends Seeder
{
    public function run(): void
    {
        $imported = app(ScholarshipProgramImportService::class)->importFromPsgcFile();

        $this->command?->info("Imported {$imported} nationwide scholarship program locations.");
    }
}
