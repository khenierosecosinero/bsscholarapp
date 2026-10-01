<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSequenceService
{
    /**
     * Columns that store a users.id reference. Other tables are left untouched.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const USER_REFERENCES = [
        ['event_registrations', 'user_id'],
        ['attendances', 'user_id'],
        ['documents', 'user_id'],
        ['documents', 'reviewed_by'],
        ['user_activities', 'user_id'],
        ['scholar_notifications', 'user_id'],
        ['announcement_reads', 'user_id'],
        ['attendance_session_logs', 'staff_id'],
        ['events', 'attendance_opened_by'],
        ['events', 'attendance_closed_by'],
        ['academic_settings', 'updated_by'],
        ['google_drive_connections', 'connected_by'],
        ['scholarship_clubs', 'created_by'],
        ['scholarship_club_schools', 'created_by'],
        ['sessions', 'user_id'],
    ];

    public function compact(): void
    {
        $ids = DB::table('users')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($ids === [] || $this->alreadySequential($ids)) {
            return;
        }

        $offset = max($ids) + count($ids) + 1;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->parkOrphanReferences($ids);

            foreach (array_reverse($ids) as $id) {
                $this->repointUserId($id, $id + $offset);
            }

            foreach ($ids as $index => $id) {
                $this->repointUserId($id + $offset, $index + 1);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

    }

    public function resetAutoIncrement(): void
    {
        $max = (int) (DB::table('users')->max('id') ?? 0);

        DB::statement('ALTER TABLE users AUTO_INCREMENT = '.($max + 1));
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function alreadySequential(array $ids): bool
    {
        foreach ($ids as $index => $id) {
            if ($id !== $index + 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Move leftover user FKs (deleted accounts) out of the 1..n range so they
     * are not attached to a remapped living user.
     *
     * @param  array<int, int>  $liveIds
     */
    private function parkOrphanReferences(array $liveIds): void
    {
        foreach (self::USER_REFERENCES as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table)->whereNotNull($column);

            if ($liveIds !== []) {
                $query->whereNotIn($column, $liveIds);
            }

            $query->update([$column => DB::raw($column.' + 100000')]);
        }
    }

    private function repointUserId(int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }

        DB::table('users')->where('id', $from)->update(['id' => $to]);

        foreach (self::USER_REFERENCES as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, $from)->update([$column => $to]);
        }
    }
}
