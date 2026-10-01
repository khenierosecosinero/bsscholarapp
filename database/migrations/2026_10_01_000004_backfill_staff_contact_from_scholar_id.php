<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy a previous Scholar Staff Number into Contact Number when staff had no cellphone saved.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->where(function ($query) {
                $query->whereNull('cellphone_number')
                    ->orWhere('cellphone_number', '');
            })
            ->whereNotNull('scholar_id')
            ->where('scholar_id', '!=', '')
            ->update([
                'cellphone_number' => DB::raw('scholar_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Contact numbers that were copied cannot be distinguished from user-entered values.
    }
};
