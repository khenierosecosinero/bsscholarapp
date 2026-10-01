<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_club_schools')) {
            Schema::create('scholarship_club_schools', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('scholarship_club_id');
                $table->string('name');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['scholarship_club_id', 'name']);
                $table->index('created_by');
            });
        }

        $this->ensureInnoDb('scholarship_club_schools');
        $this->tryForeignKey('scholarship_club_schools', 'scholarship_club_id', 'scholarship_clubs', 'id', 'cascade');
        $this->tryForeignKey('scholarship_club_schools', 'created_by', 'users', 'id', 'set null');

        if (! Schema::hasColumn('users', 'scholarship_club_school_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('scholarship_club_school_id')
                    ->nullable()
                    ->after('scholarship_club_id');
                $table->index('scholarship_club_school_id');
            });
        }

        $this->tryForeignKey('users', 'scholarship_club_school_id', 'scholarship_club_schools', 'id', 'set null');
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'scholarship_club_school_id')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->hasForeignKey('users', 'users_scholarship_club_school_id_foreign')) {
                    $table->dropForeign('users_scholarship_club_school_id_foreign');
                }

                $table->dropColumn('scholarship_club_school_id');
            });
        }

        Schema::dropIfExists('scholarship_club_schools');
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
