<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => User::PERMANENT_ADMIN_EMAIL],
            [
                'full_name' => 'BSSA Administrator',
                'scholar_id' => User::PERMANENT_ADMIN_SCHOLAR_ID,
                'password' => '1234512345',
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
}
