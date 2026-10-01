<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('scholarship_clubs', 'province')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->string('province')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('scholarship_clubs', 'city')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->string('city')->nullable()->after('province');
            });
        }

        foreach (DB::table('scholarship_clubs')->get() as $club) {
            if (filled($club->province) && filled($club->city)) {
                continue;
            }

            $program = DB::table('scholarship_programs')->find($club->scholarship_program_id);

            if (! $program) {
                continue;
            }

            $city = $program->location_type === 'city_municipality' ? $program->location_name : null;
            $province = $program->location_type === 'province'
                ? $program->location_name
                : ($program->province_name ?: null);

            DB::table('scholarship_clubs')->where('id', $club->id)->update([
                'city' => $club->city ?: $city,
                'province' => $club->province ?: $province,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('scholarship_clubs', function (Blueprint $table) {
            foreach (['city', 'province'] as $column) {
                if (Schema::hasColumn('scholarship_clubs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
