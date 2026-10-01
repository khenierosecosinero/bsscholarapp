<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_clubs')) {
            Schema::create('scholarship_clubs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('scholarship_program_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['scholarship_program_id', 'name']);
                $table->index('scholarship_program_id');
                $table->index('created_by');
            });
        }

        $this->ensureInnoDb('scholarship_clubs');

        if (! Schema::hasColumn('users', 'scholarship_club_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('scholarship_club_id')
                    ->nullable()
                    ->after('scholarship_program_id');
                $table->index('scholarship_club_id');
            });
        }

        $this->tryForeignKey('scholarship_clubs', 'scholarship_program_id', 'scholarship_programs', 'id', 'cascade');
        $this->tryForeignKey('scholarship_clubs', 'created_by', 'users', 'id', 'set null');
        $this->tryForeignKey('users', 'scholarship_club_id', 'scholarship_clubs', 'id', 'set null');

        $now = now();

        foreach (DB::table('scholarship_programs')->get() as $program) {
            $unassigned = DB::table('users')
                ->where('scholarship_program_id', $program->id)
                ->whereNull('scholarship_club_id');

            if (! $unassigned->exists()) {
                continue;
            }

            $clubId = DB::table('scholarship_clubs')
                ->where('scholarship_program_id', $program->id)
                ->value('id');

            if (! $clubId) {
                $clubId = DB::table('scholarship_clubs')->insertGetId([
                    'name' => $program->display_name ?: $program->name ?: $program->location_name,
                    'scholarship_program_id' => $program->id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('users')
                ->where('scholarship_program_id', $program->id)
                ->whereNull('scholarship_club_id')
                ->update(['scholarship_club_id' => $clubId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'scholarship_club_id')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->hasForeignKey('users', 'users_scholarship_club_id_foreign')) {
                    $table->dropForeign('users_scholarship_club_id_foreign');
                }

                $table->dropColumn('scholarship_club_id');
            });
        }

        Schema::dropIfExists('scholarship_clubs');
    }

    private function ensureInnoDb(string $table): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        $status = DB::select('SHOW TABLE STATUS WHERE Name = ?', [$table]);
        $engine = $status[0]->Engine ?? null;

        if ($engine && strcasecmp((string) $engine, 'InnoDB') !== 0) {
            DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
        }
    }

    private function tryForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete
    ): void {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $name = $table.'_'.$column.'_foreign';

        if ($this->hasForeignKey($table, $name)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $blueprint->foreign($column)
                    ->references($referencedColumn)
                    ->on($referencedTable)
                    ->onDelete($onDelete);
            });
        } catch (\Throwable) {
            // Mixed MyISAM/InnoDB catalogs cannot accept the constraint.
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
