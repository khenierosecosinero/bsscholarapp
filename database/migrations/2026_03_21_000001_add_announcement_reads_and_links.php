<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['user_id', 'announcement_id']);
        });

        Schema::table('scholar_notifications', function (Blueprint $table) {
            $table->foreignId('announcement_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scholar_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announcement_id');
        });

        Schema::dropIfExists('announcement_reads');
    }
};
