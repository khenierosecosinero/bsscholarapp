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
            return;
        }

        if (! Schema::hasColumn('scholarship_clubs', 'name_key')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->string('name_key', 255)->nullable()->after('name');
            });
        }

        $seen = [];

        foreach (DB::table('scholarship_clubs')->orderBy('id')->get(['id', 'name', 'name_key']) as $club) {
            $normalized = trim(preg_replace('/\s+/', ' ', (string) $club->name) ?? (string) $club->name);
            $key = mb_strtolower($normalized);

            if ($key === '') {
                $key = 'club-'.$club->id;
            }

            if (isset($seen[$key])) {
                // Preserve existing rows that already shared a name across programs.
                $key = $key.'#'.$club->id;
            }

            $seen[$key] = true;

            DB::table('scholarship_clubs')->where('id', $club->id)->update([
                'name_key' => $key,
            ]);
        }

        $this->dropCompositeNameUnique();

        if (! $this->hasIndex('scholarship_clubs', 'scholarship_clubs_name_key_unique')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->unique('name_key', 'scholarship_clubs_name_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('scholarship_clubs')) {
            return;
        }

        if ($this->hasIndex('scholarship_clubs', 'scholarship_clubs_name_key_unique')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->dropUnique('scholarship_clubs_name_key_unique');
            });
        }

        if (Schema::hasColumn('scholarship_clubs', 'name_key')) {
            Schema::table('scholarship_clubs', function (Blueprint $table) {
                $table->dropColumn('name_key');
            });
        }
    }

    private function dropCompositeNameUnique(): void
    {
        $candidates = [
            'scholarship_clubs_scholarship_program_id_name_unique',
            'scholarship_program_id_name_unique',
        ];

        foreach ($candidates as $name) {
            if (! $this->hasIndex('scholarship_clubs', $name)) {
                continue;
            }

            Schema::table('scholarship_clubs', function (Blueprint $table) use ($name) {
                $table->dropUnique($name);
            });

            return;
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list(`'.$table.'`)');

            foreach ($indexes as $index) {
                if (strcasecmp((string) ($index->name ?? ''), $name) === 0) {
                    return true;
                }
            }

            return false;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $name)
            ->exists();
    }
};
