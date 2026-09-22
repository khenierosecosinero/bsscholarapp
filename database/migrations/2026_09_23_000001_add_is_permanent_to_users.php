<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_permanent')->default(false)->after('is_admin');
        });

        DB::table('users')
            ->where('email', 'bssa_admin@gmail.com')
            ->orWhere('scholar_id', 'ADMIN-001')
            ->update([
                'is_permanent' => true,
                'is_admin' => true,
                'role' => 'admin',
                'status' => 'approved',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_permanent');
        });
    }
};
