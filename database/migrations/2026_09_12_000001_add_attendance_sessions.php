<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('attendance_is_open')->default(false)->after('status');
            $table->timestamp('attendance_opened_at')->nullable()->after('attendance_is_open');
            $table->timestamp('attendance_closed_at')->nullable()->after('attendance_opened_at');
            $table->foreignId('attendance_opened_by')->nullable()->after('attendance_closed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('attendance_closed_by')->nullable()->after('attendance_opened_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('scholar_notifications', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('announcement_id')->constrained()->nullOnDelete();
        });

        Schema::create('attendance_session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_session_logs');

        Schema::table('scholar_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_opened_by');
            $table->dropConstrainedForeignId('attendance_closed_by');
            $table->dropColumn([
                'attendance_is_open',
                'attendance_opened_at',
                'attendance_closed_at',
            ]);
        });
    }
};
