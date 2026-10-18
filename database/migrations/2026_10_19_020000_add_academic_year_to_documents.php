<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'academic_year_start')) {
                $table->unsignedSmallInteger('academic_year_start')->nullable()->after('document_type_id');
            }
            if (! Schema::hasColumn('documents', 'academic_year_end')) {
                $table->unsignedSmallInteger('academic_year_end')->nullable()->after('academic_year_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'academic_year_end')) {
                $table->dropColumn('academic_year_end');
            }
            if (Schema::hasColumn('documents', 'academic_year_start')) {
                $table->dropColumn('academic_year_start');
            }
        });
    }
};
