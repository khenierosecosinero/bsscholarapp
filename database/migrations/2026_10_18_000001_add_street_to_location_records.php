<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'street')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('street')->nullable()->after('province');
            });
        }

        if (Schema::hasTable('scholarship_clubs') && ! Schema::hasColumn('scholarship_clubs', 'street')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->string('street')->nullable()->after('city');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'street')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('street');
            });
        }

        if (Schema::hasTable('scholarship_clubs') && Schema::hasColumn('scholarship_clubs', 'street')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->dropColumn('street');
            });
        }
    }
};
