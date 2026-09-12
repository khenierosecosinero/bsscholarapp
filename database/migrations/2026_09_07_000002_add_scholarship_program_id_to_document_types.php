<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->foreignId('scholarship_program_id')
                ->nullable()
                ->after('id')
                ->constrained('scholarship_programs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scholarship_program_id');
        });
    }
};
