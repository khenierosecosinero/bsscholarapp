<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->string('psgc_code', 16)->nullable()->unique()->after('id');
            $table->string('location_type', 32)->nullable()->after('location_name');
            $table->string('display_name')->nullable()->after('name');
            $table->string('province_name')->nullable()->after('display_name');
            $table->string('region_name')->nullable()->after('province_name');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->dropColumn([
                'psgc_code',
                'location_type',
                'display_name',
                'province_name',
                'region_name',
            ]);
        });
    }
};
