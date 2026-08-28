<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'bssa_admin@gmail.com'],
            [
                'full_name' => 'BSSA Administrator',
                'scholar_id' => 'ADMIN-001',
                'password' => '1234512345',
                'role' => User::ROLE_ADMIN,
                'is_admin' => true,
                'status' => User::STATUS_APPROVED,
            ]
        );
    }
}
