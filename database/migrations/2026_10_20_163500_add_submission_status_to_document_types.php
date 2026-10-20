<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('submissions_open')->default(true)->after('required');
            $table->timestamp('submissions_opened_at')->nullable()->after('submissions_open');
            $table->timestamp('submissions_closed_at')->nullable()->after('submissions_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn(['submissions_open', 'submissions_opened_at', 'submissions_closed_at']);
        });
    }
};
