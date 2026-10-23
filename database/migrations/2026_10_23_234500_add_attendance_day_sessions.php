<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_day_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->boolean('is_open')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'session_date']);
        });

        Schema::table('attendance_session_logs', function (Blueprint $table) {
            $table->date('session_date')->nullable()->after('event_id');
        });

        $timezone = config('app.timezone');

        DB::table('attendance_session_logs')->orderBy('id')->lazyById()->each(function ($log) use ($timezone) {
            $date = Carbon::parse($log->acted_at)->timezone($timezone)->toDateString();
            DB::table('attendance_session_logs')->where('id', $log->id)->update(['session_date' => $date]);
        });

        DB::table('events')
            ->where(function ($query) {
                $query->where('attendance_is_open', true)
                    ->orWhereNotNull('attendance_opened_at')
                    ->orWhereNotNull('attendance_closed_at');
            })
            ->orderBy('id')
            ->lazyById()
            ->each(function ($event) use ($timezone) {
                $source = $event->attendance_opened_at ?: $event->starts_at;
                $date = Carbon::parse($source)->timezone($timezone)->toDateString();

                DB::table('attendance_day_sessions')->updateOrInsert(
                    ['event_id' => $event->id, 'session_date' => $date],
                    [
                        'is_open' => (bool) $event->attendance_is_open,
                        'opened_at' => $event->attendance_opened_at,
                        'closed_at' => $event->attendance_closed_at,
                        'opened_by' => $event->attendance_opened_by,
                        'closed_by' => $event->attendance_closed_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::table('attendance_session_logs', function (Blueprint $table) {
            $table->dropColumn('session_date');
        });

        Schema::dropIfExists('attendance_day_sessions');
    }
};
