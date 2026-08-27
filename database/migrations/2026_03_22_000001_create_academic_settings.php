<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year_start');
            $table->unsignedSmallInteger('year_end');
            $table->string('semester', 32);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('status');
            $table->unsignedSmallInteger('academic_year_start')->nullable()->after('is_admin');
            $table->string('semester', 32)->nullable()->after('academic_year_start');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('academic_year_start')->nullable()->after('event_id');
            $table->unsignedSmallInteger('academic_year_end')->nullable()->after('academic_year_start');
            $table->string('semester', 32)->nullable()->after('academic_year_end');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['academic_year_start', 'academic_year_end', 'semester']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'academic_year_start', 'semester']);
        });

        Schema::dropIfExists('academic_settings');
    }
};
