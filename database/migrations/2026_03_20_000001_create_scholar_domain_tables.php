<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('year_level')->nullable()->after('course_year_level');
            $table->date('date_of_birth')->nullable()->after('year_level');
            $table->string('guardian_name')->nullable()->after('date_of_birth');
            $table->string('guardian_relationship')->nullable()->after('guardian_name');
            $table->string('guardian_cellphone')->nullable()->after('guardian_relationship');
            $table->string('avatar_path')->nullable()->after('guardian_cellphone');
            $table->string('status')->default('approved')->after('avatar_path');
            $table->json('notification_preferences')->nullable()->after('status');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('service_hours', 5, 2)->default(0);
            $table->string('organizer')->nullable();
            $table->string('image_url')->nullable();
            $table->string('status')->default('upcoming');
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('confirmed');
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->decimal('hours_earned', 5, 2)->nullable();
            $table->string('status')->default('pending');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('status')->default('not_submitted');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'document_type_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('scholar_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('category')->default('system');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_important')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholar_notifications');
        Schema::dropIfExists('user_activities');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'year_level', 'date_of_birth', 'guardian_name',
                'guardian_relationship', 'guardian_cellphone',
                'avatar_path', 'status', 'notification_preferences',
            ]);
        });
    }
};
