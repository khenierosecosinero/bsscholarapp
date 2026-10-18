<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'staff_started_year')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedSmallInteger('staff_started_year')->nullable()->after('program_start_year');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'staff_started_year')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('staff_started_year');
            });
        }
    }
};
