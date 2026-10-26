<?php

use App\Models\ScholarshipProgram;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addClubColumn('events');
        $this->addClubColumn('document_types');
        $this->addClubColumn('announcements');
        $this->assignSoleClub('events');
        $this->assignSoleClub('document_types');
        $this->assignSoleClub('announcements');
        $this->correctLocationNames();
    }

    public function down(): void
    {
        foreach (['announcements', 'document_types', 'events'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'scholarship_club_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $foreign = $table.'_scholarship_club_id_foreign';
                if ($this->hasForeignKey($table, $foreign)) {
                    $blueprint->dropForeign($foreign);
                }
                $blueprint->dropColumn('scholarship_club_id');
            });
        }
    }

    private function addClubColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'scholarship_club_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('scholarship_club_id')->nullable()->after('scholarship_program_id');
            $blueprint->index('scholarship_club_id');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite' || ! Schema::hasTable('scholarship_clubs')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->foreign('scholarship_club_id', $table.'_scholarship_club_id_foreign')
                ->references('id')
                ->on('scholarship_clubs')
                ->nullOnDelete();
        });
    }

    private function assignSoleClub(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'scholarship_club_id') || ! Schema::hasTable('scholarship_clubs')) {
            return;
        }

        $clubs = DB::table('scholarship_clubs')
            ->select('scholarship_program_id', DB::raw('COUNT(*) as club_count'), DB::raw('MIN(id) as club_id'))
            ->whereNotNull('scholarship_program_id')
            ->groupBy('scholarship_program_id')
            ->get();

        foreach ($clubs as $club) {
            if ((int) $club->club_count !== 1) {
                continue;
            }

            DB::table($table)
                ->where('scholarship_program_id', $club->scholarship_program_id)
                ->whereNull('scholarship_club_id')
                ->update(['scholarship_club_id' => $club->club_id]);
        }
    }

    /**
     * Location rows place a club. Only Surigao City keeps a scholarship-program name.
     */
    private function correctLocationNames(): void
    {
        if (! Schema::hasTable('scholarship_programs')) {
            return;
        }

        DB::table('scholarship_programs')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $location = trim((string) $row->location_name);
                $stored = ScholarshipProgram::storedNameForLocation(
                    $location,
                    (string) $row->location_type,
                    $row->province_name
                );

                if ($row->name === $location.' Scholarship Program' || $stored === 'Surigao City Scholarship Program') {
                    DB::table('scholarship_programs')->where('id', $row->id)->update([
                        'name' => $stored,
                    ]);
                }
            });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA foreign_key_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->from ?? null) === 'scholarship_club_id') {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();
        $found = $connection->select(
            'select CONSTRAINT_NAME from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = ? and TABLE_NAME = ? and CONSTRAINT_NAME = ?',
            [$database, $table, $name]
        );

        return $found !== [];
    }
};
