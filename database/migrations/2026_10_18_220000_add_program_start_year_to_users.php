<?php

use App\Models\User;
use App\Services\ScholarService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'program_start_year')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedSmallInteger('program_start_year')->nullable()->after('year_level');
            });
        }

        $adminYear = (int) (DB::table('academic_settings')
            ->where('is_active', true)
            ->orderByDesc('year_start')
            ->value('year_start') ?: now()->year);

        DB::table('users')
            ->where('role', User::ROLE_SCHOLAR)
            ->whereNull('program_start_year')
            ->orderBy('id')
            ->get(['id', 'year_level', 'academic_year_start'])
            ->each(function (object $scholar) use ($adminYear) {
                $reference = $scholar->academic_year_start ?: $adminYear;
                $start = max(2000, (int) $reference - (ScholarService::programYearIndex($scholar->year_level) - 1));

                DB::table('users')
                    ->where('id', $scholar->id)
                    ->update(['program_start_year' => $start]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'program_start_year')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('program_start_year');
            });
        }
    }
};
