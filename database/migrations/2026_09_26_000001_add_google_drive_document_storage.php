<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_drive_folder_id')->nullable()->after('events_visible_from');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('google_drive_file_id')->nullable()->after('file_path');
            $table->string('google_drive_web_link')->nullable()->after('google_drive_file_id');
        });

        Schema::create('google_drive_folders', function (Blueprint $table) {
            $table->id();
            $table->string('folder_key')->unique();
            $table->string('folder_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('google_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->text('token');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_drive_connections');
        Schema::dropIfExists('google_drive_folders');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['google_drive_file_id', 'google_drive_web_link']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_drive_folder_id');
        });
    }
};
