<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AdminBackupEmailService;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('scholar_id', User::PERMANENT_ADMIN_SCHOLAR_ID)
            ->orWhere('is_permanent', true)
            ->orWhere('email', User::PERMANENT_ADMIN_EMAIL)
            ->orWhere('email', 'bssa_admin@gmail.com')
            ->orderByDesc('is_permanent')
            ->first();

        if ($admin) {
            $admin->forceFill([
                'full_name' => $admin->full_name ?: 'BSSA Administrator',
                'email' => User::PERMANENT_ADMIN_EMAIL,
                'scholar_id' => User::PERMANENT_ADMIN_SCHOLAR_ID,
                'password' => 'bssa_system2022',
                'role' => User::ROLE_ADMIN,
                'is_admin' => true,
                'is_permanent' => true,
                'status' => User::STATUS_APPROVED,
            ])->save();
        } else {
            $admin = User::query()->updateOrCreate(
                ['email' => User::PERMANENT_ADMIN_EMAIL],
                [
                    'full_name' => 'BSSA Administrator',
                    'scholar_id' => User::PERMANENT_ADMIN_SCHOLAR_ID,
                    'password' => 'bssa_system2022',
                    'role' => User::ROLE_ADMIN,
                    'is_admin' => true,
                    'status' => User::STATUS_APPROVED,
                ]
            );

            $admin->forceFill([
                'is_permanent' => true,
                'is_admin' => true,
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_APPROVED,
            ])->save();
        }

        // Stage initial backup address if missing; recovery stays off until verified.
        app(AdminBackupEmailService::class)->stageDefaultPermanentAdminBackup(
            $admin->fresh(),
            sendMail: false
        );
    }
}
