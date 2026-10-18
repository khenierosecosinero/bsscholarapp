<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_backup_emails')) {
            Schema::create('admin_backup_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('email');
                $table->timestamp('verified_at')->nullable();
                $table->string('verify_token', 100)->nullable();
                $table->timestamp('verify_sent_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'email']);
                $table->index('email');
            });
        }

        // Migrate any legacy single-column backup data, then drop those columns.
        if (Schema::hasColumn('users', 'backup_email') || Schema::hasColumn('users', 'backup_email_pending')) {
            $rows = DB::table('users')
                ->select([
                    'id',
                    'backup_email',
                    'backup_email_verified_at',
                    'backup_email_pending',
                    'backup_email_verify_token',
                    'backup_email_verify_sent_at',
                ])
                ->get();

            foreach ($rows as $row) {
                $verified = strtolower(trim((string) ($row->backup_email ?? '')));
                if ($verified !== '' && $row->backup_email_verified_at !== null) {
                    DB::table('admin_backup_emails')->updateOrInsert(
                        ['user_id' => $row->id, 'email' => $verified],
                        [
                            'verified_at' => $row->backup_email_verified_at,
                            'verify_token' => null,
                            'verify_sent_at' => null,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                $pending = strtolower(trim((string) ($row->backup_email_pending ?? '')));
                if ($pending !== '') {
                    DB::table('admin_backup_emails')->updateOrInsert(
                        ['user_id' => $row->id, 'email' => $pending],
                        [
                            'verified_at' => null,
                            'verify_token' => $row->backup_email_verify_token,
                            'verify_sent_at' => $row->backup_email_verify_sent_at,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            Schema::table('users', function (Blueprint $table) {
                foreach ([
                    'backup_email_verify_sent_at',
                    'backup_email_verify_token',
                    'backup_email_pending',
                    'backup_email_verified_at',
                    'backup_email',
                ] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_backup_emails');
    }
};
