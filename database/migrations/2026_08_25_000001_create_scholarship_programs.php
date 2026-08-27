<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_programs', function (Blueprint $table) {
            $table->id();
            $table->string('location_name');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('scholar')->after('is_admin');
            $table->foreignId('scholarship_program_id')
                ->nullable()
                ->after('role')
                ->constrained('scholarship_programs')
                ->nullOnDelete();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('scholarship_program_id')
                ->nullable()
                ->after('status')
                ->constrained('scholarship_programs')
                ->nullOnDelete();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('scholarship_program_id')
                ->nullable()
                ->after('published_at')
                ->constrained('scholarship_programs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scholarship_program_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scholarship_program_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scholarship_program_id');
            $table->dropColumn('role');
        });

        Schema::dropIfExists('scholarship_programs');
    }
};
