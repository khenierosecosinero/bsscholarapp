<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'google_drive_folder_id')) {
                $table->string('google_drive_folder_id')->nullable()->after('photo_uploaded_at');
            }

            if (! Schema::hasColumn('attendances', 'google_drive_file_id')) {
                $table->string('google_drive_file_id')->nullable()->after('google_drive_folder_id');
            }

            if (! Schema::hasColumn('attendances', 'google_drive_web_link')) {
                $table->string('google_drive_web_link')->nullable()->after('google_drive_file_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('attendances', 'google_drive_folder_id') ? 'google_drive_folder_id' : null,
                Schema::hasColumn('attendances', 'google_drive_file_id') ? 'google_drive_file_id' : null,
                Schema::hasColumn('attendances', 'google_drive_web_link') ? 'google_drive_web_link' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
