<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing accounts predate email verification. Mark them verified so
        // they are not locked behind the new notice screen. New scholar
        // registrations still receive a verification email.
        $now = now();

        DB::table('users')
            ->whereNull('email_verified_at')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($now) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'email_verified_at' => $user->created_at ?: $now,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive: do not clear verification timestamps on rollback.
    }
};
