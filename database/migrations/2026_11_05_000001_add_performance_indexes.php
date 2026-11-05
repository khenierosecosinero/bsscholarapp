<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('users', 'users_role_program_status_index', ['role', 'scholarship_program_id', 'status']);
        $this->addIndex('events', 'events_program_starts_at_index', ['scholarship_program_id', 'starts_at']);
        $this->addIndex('events', 'events_status_ends_at_index', ['status', 'ends_at']);
        $this->addIndex('attendances', 'attendances_year_status_index', ['academic_year_start', 'status']);
        $this->addIndex('attendances', 'attendances_status_check_in_index', ['status', 'check_in']);
        $this->addIndex('documents', 'documents_status_year_index', ['status', 'academic_year_start']);
        $this->addIndex('event_registrations', 'event_registrations_status_event_index', ['status', 'event_id']);
        $this->addIndex('scholar_notifications', 'scholar_notifications_user_read_index', ['user_id', 'is_read']);
        $this->addIndex('user_activities', 'user_activities_user_created_index', ['user_id', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndex('users', 'users_role_program_status_index');
        $this->dropIndex('events', 'events_program_starts_at_index');
        $this->dropIndex('events', 'events_status_ends_at_index');
        $this->dropIndex('attendances', 'attendances_year_status_index');
        $this->dropIndex('attendances', 'attendances_status_check_in_index');
        $this->dropIndex('documents', 'documents_status_year_index');
        $this->dropIndex('event_registrations', 'event_registrations_status_event_index');
        $this->dropIndex('scholar_notifications', 'scholar_notifications_user_read_index');
        $this->dropIndex('user_activities', 'user_activities_user_created_index');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $definition) => ($definition['name'] ?? '') === $index
        );
    }
};
