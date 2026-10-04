<?php

use App\Models\ScholarshipClub;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_clubs')) {
            return;
        }

        ScholarshipClub::removeRetiredClubs();
    }

    public function down(): void
    {
        // Retired Scholarship Clubs must not be restored.
    }
};
