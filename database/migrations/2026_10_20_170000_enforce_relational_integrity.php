<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The live catalog was created as MyISAM, which ignores foreign keys.
 * Deleting an event, user, or document type therefore left registrations,
 * attendance, notifications, and other rows behind. Those rows can attach to
 * a later record if an identifier is reused or a screen lists them by title.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->removeOrphanRows();

        foreach ($this->tables() as $table) {
            $this->ensureInnoDb($table);
        }

        $this->addForeignKeys();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_reverse($this->foreignKeys()) as [$table, $column]) {
            $name = $table.'_'.$column.'_foreign';
            if ($this->hasForeignKey($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropForeign($name);
                });
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'users',
            'scholarship_programs',
            'scholarship_clubs',
            'scholarship_club_schools',
            'events',
            'event_registrations',
            'attendances',
            'document_types',
            'documents',
            'announcements',
            'announcement_reads',
            'scholar_notifications',
            'user_activities',
            'scholar_suspensions',
            'attendance_session_logs',
            'academic_settings',
            'google_drive_connections',
            'admin_backup_emails',
            'sessions',
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['users', 'scholarship_program_id', 'scholarship_programs', 'null'],
            ['users', 'scholarship_club_id', 'scholarship_clubs', 'null'],
            ['users', 'scholarship_club_school_id', 'scholarship_club_schools', 'null'],
            ['scholarship_clubs', 'scholarship_program_id', 'scholarship_programs', 'cascade'],
            ['scholarship_clubs', 'created_by', 'users', 'null'],
            ['scholarship_club_schools', 'created_by', 'users', 'null'],
            ['events', 'scholarship_program_id', 'scholarship_programs', 'null'],
            ['events', 'attendance_opened_by', 'users', 'null'],
            ['events', 'attendance_closed_by', 'users', 'null'],
            ['event_registrations', 'user_id', 'users', 'cascade'],
            ['event_registrations', 'event_id', 'events', 'cascade'],
            ['attendances', 'user_id', 'users', 'cascade'],
            ['attendances', 'event_id', 'events', 'cascade'],
            ['document_types', 'scholarship_program_id', 'scholarship_programs', 'null'],
            ['documents', 'user_id', 'users', 'cascade'],
            ['documents', 'document_type_id', 'document_types', 'cascade'],
            ['documents', 'reviewed_by', 'users', 'null'],
            ['announcements', 'scholarship_program_id', 'scholarship_programs', 'null'],
            ['announcement_reads', 'user_id', 'users', 'cascade'],
            ['announcement_reads', 'announcement_id', 'announcements', 'cascade'],
            ['scholar_notifications', 'user_id', 'users', 'cascade'],
            ['scholar_notifications', 'announcement_id', 'announcements', 'null'],
            ['scholar_notifications', 'event_id', 'events', 'null'],
            ['user_activities', 'user_id', 'users', 'cascade'],
            ['scholar_suspensions', 'user_id', 'users', 'cascade'],
            ['scholar_suspensions', 'issued_by', 'users', 'null'],
            ['scholar_suspensions', 'lifted_by', 'users', 'null'],
            ['attendance_session_logs', 'event_id', 'events', 'cascade'],
            ['attendance_session_logs', 'staff_id', 'users', 'cascade'],
            ['academic_settings', 'updated_by', 'users', 'null'],
            ['google_drive_connections', 'connected_by', 'users', 'null'],
            ['admin_backup_emails', 'user_id', 'users', 'cascade'],
            ['sessions', 'user_id', 'users', 'cascade'],
        ];
    }

    private function removeOrphanRows(): void
    {
        $this->deleteMissingParent('event_registrations', 'event_id', 'events');
        $this->deleteMissingParent('event_registrations', 'user_id', 'users');
        $this->deleteMissingParent('attendances', 'event_id', 'events');
        $this->deleteMissingParent('attendances', 'user_id', 'users');
        $this->deleteMissingParent('documents', 'user_id', 'users');
        $this->deleteMissingParent('documents', 'document_type_id', 'document_types');
        $this->deleteMissingParent('scholar_notifications', 'user_id', 'users');
        $this->deleteMissingParent('user_activities', 'user_id', 'users');
        $this->deleteMissingParent('announcement_reads', 'user_id', 'users');
        $this->deleteMissingParent('announcement_reads', 'announcement_id', 'announcements');
        $this->deleteMissingParent('scholar_suspensions', 'user_id', 'users');
        $this->deleteMissingParent('attendance_session_logs', 'event_id', 'events');
        $this->deleteMissingParent('attendance_session_logs', 'staff_id', 'users');
        $this->deleteMissingParent('admin_backup_emails', 'user_id', 'users');
        $this->deleteMissingParent('sessions', 'user_id', 'users');

        $this->nullMissingParent('documents', 'reviewed_by', 'users');
        $this->nullMissingParent('scholar_notifications', 'event_id', 'events');
        $this->nullMissingParent('scholar_notifications', 'announcement_id', 'announcements');
        $this->nullMissingParent('scholar_suspensions', 'issued_by', 'users');
        $this->nullMissingParent('scholar_suspensions', 'lifted_by', 'users');
        $this->nullMissingParent('events', 'scholarship_program_id', 'scholarship_programs');
        $this->nullMissingParent('events', 'attendance_opened_by', 'users');
        $this->nullMissingParent('events', 'attendance_closed_by', 'users');
        $this->nullMissingParent('users', 'scholarship_program_id', 'scholarship_programs');
        $this->nullMissingParent('users', 'scholarship_club_id', 'scholarship_clubs');
        $this->nullMissingParent('users', 'scholarship_club_school_id', 'scholarship_club_schools');
        $this->nullMissingParent('document_types', 'scholarship_program_id', 'scholarship_programs');
        $this->nullMissingParent('announcements', 'scholarship_program_id', 'scholarship_programs');
        $this->nullMissingParent('academic_settings', 'updated_by', 'users');
        $this->nullMissingParent('google_drive_connections', 'connected_by', 'users');
        $this->nullMissingParent('scholarship_clubs', 'created_by', 'users');
        $this->nullMissingParent('scholarship_club_schools', 'created_by', 'users');
    }

    private function addForeignKeys(): void
    {
        foreach ($this->foreignKeys() as [$table, $column, $referenced, $onDelete]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $name = $table.'_'.$column.'_foreign';
            if ($this->hasForeignKey($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced, $onDelete) {
                $foreign = $blueprint->foreign($column)->references('id')->on($referenced);
                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        }
    }

    private function deleteMissingParent(string $table, string $column, string $parent): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parent)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, function ($query) use ($parent) {
                $query->select('id')->from($parent);
            })
            ->delete();
    }

    private function nullMissingParent(string $table, string $column, string $parent): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parent)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, function ($query) use ($parent) {
                $query->select('id')->from($parent);
            })
            ->update([$column => null]);
    }

    private function ensureInnoDb(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $status = DB::select('SHOW TABLE STATUS WHERE Name = ?', [$table]);
        $engine = $status[0]->Engine ?? null;

        if ($engine && strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
