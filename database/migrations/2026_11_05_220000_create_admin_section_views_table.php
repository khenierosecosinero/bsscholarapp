<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_section_views')) {
            return;
        }

        Schema::create('admin_section_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('section', 32);
            $table->timestamp('viewed_at')->nullable();
            $table->text('seen_signature')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_section_views');
    }
};
