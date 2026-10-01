<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_settings', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('semester');
        });

        $activeId = DB::table('academic_settings')->orderBy('id')->value('id');
        if ($activeId) {
            DB::table('academic_settings')->where('id', $activeId)->update(['is_active' => true]);
        }

        Schema::table('academic_settings', function (Blueprint $table) {
            $table->unique('year_start');
        });
    }

    public function down(): void
    {
        Schema::table('academic_settings', function (Blueprint $table) {
            $table->dropUnique(['year_start']);
            $table->dropColumn('is_active');
        });
    }
};
