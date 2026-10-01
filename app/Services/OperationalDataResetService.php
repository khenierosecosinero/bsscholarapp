<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OperationalDataResetService
{
    /**
     * Tables emptied on a clean-slate reset. Schema, scholarship_programs,
     * academic_settings, and the permanent admin row stay.
     *
     * @var list<string>
     */
    public const OPERATIONAL_TABLES = [
        'announcement_reads',
        'scholar_notifications',
        'user_activities',
        'documents',
        'document_types',
        'google_drive_folders',
        'attendances',
        'attendance_session_logs',
        'event_registrations',
        'events',
        'announcements',
        'scholarship_club_schools',
        'scholarship_clubs',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * Public-disk folders that hold uploaded files.
     *
     * @var list<string>
     */
    public const UPLOAD_DIRECTORIES = [
        'avatars',
        'documents',
        'attendance_photos',
        'event_images',
    ];

    /**
     * @return array{
     *     admin_email: string,
     *     users: int,
     *     scholarship_programs: int,
     *     academic_year: string,
     *     events: int,
     *     attendances: int,
     *     documents: int,
     *     document_types: int,
     *     announcements: int,
     *     notifications: int
     * }
     */
    public function reset(): array
    {
        $keepIds = User::query()
            ->get()
            ->filter(fn (User $user) => $user->isPermanentAdmin())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($keepIds === []) {
            throw new RuntimeException('Permanent admin account was not found. Reset aborted.');
        }

        $this->clearUploadedFiles();

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (self::OPERATIONAL_TABLES as $table) {
                $this->emptyTable($table);
            }

            DB::table('users')->whereNotIn('id', $keepIds)->delete();
            $this->clearAdminSessionFields($keepIds);
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $sequence = app(UserSequenceService::class);
        $sequence->compact();
        $sequence->resetAutoIncrement();

        $this->resetAcademicSettings();
        $this->clearApplicationCaches();

        return $this->snapshot();
    }

    /**
     * @param  list<int>  $keepIds
     */
    private function clearAdminSessionFields(array $keepIds): void
    {
        $clears = [];

        foreach (['last_seen_at', 'last_login_at', 'remember_token', 'avatar_path', 'events_visible_from'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $clears[$column] = null;
            }
        }

        if ($clears === []) {
            return;
        }

        DB::table('users')->whereIn('id', $keepIds)->update($clears);
    }

    private function emptyTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table($table)->truncate();

            return;
        }

        DB::table($table)->delete();

        if (DB::getDriverName() === 'sqlite') {
            DB::table('sqlite_sequence')->where('name', $table)->delete();
        }
    }

    private function resetAcademicSettings(): void
    {
        $admin = User::query()
            ->get()
            ->first(fn (User $user) => $user->isPermanentAdmin());

        $yearStart = (int) now()->year;

        AcademicSetting::query()->delete();
        AcademicSetting::create([
            'year_start' => $yearStart,
            'year_end' => $yearStart + 1,
            'semester' => '2nd Semester',
            'updated_by' => $admin?->id,
            'is_active' => true,
        ]);

        app(AcademicSettingsService::class)->clearCache();
    }

    private function clearUploadedFiles(): void
    {
        $disk = Storage::disk('public');

        foreach (self::UPLOAD_DIRECTORIES as $directory) {
            if ($disk->exists($directory)) {
                $disk->deleteDirectory($directory);
            }

            $disk->makeDirectory($directory);
        }

        foreach ($disk->files() as $file) {
            if (basename($file) === '.gitignore') {
                continue;
            }

            $disk->delete($file);
        }
    }

    private function clearApplicationCaches(): void
    {
        Cache::flush();
        Artisan::call('optimize:clear');
    }

    /**
     * @return array{
     *     admin_email: string,
     *     users: int,
     *     scholarship_programs: int,
     *     academic_year: string,
     *     events: int,
     *     attendances: int,
     *     documents: int,
     *     document_types: int,
     *     announcements: int,
     *     notifications: int
     * }
     */
    private function snapshot(): array
    {
        $admin = User::query()
            ->get()
            ->first(fn (User $user) => $user->isPermanentAdmin());

        $setting = AcademicSetting::query()->active()->orderByDesc('year_start')->first()
            ?? AcademicSetting::query()->orderByDesc('year_start')->first();
        $yearLabel = $setting
            ? $setting->year_start.'–'.$setting->year_end.' / '.$setting->semester
            : '';

        return [
            'admin_email' => (string) ($admin?->email ?? ''),
            'users' => (int) DB::table('users')->count(),
            'scholarship_programs' => Schema::hasTable('scholarship_programs')
                ? (int) DB::table('scholarship_programs')->count()
                : 0,
            'academic_year' => $yearLabel,
            'events' => Schema::hasTable('events') ? (int) DB::table('events')->count() : 0,
            'attendances' => Schema::hasTable('attendances') ? (int) DB::table('attendances')->count() : 0,
            'documents' => Schema::hasTable('documents') ? (int) DB::table('documents')->count() : 0,
            'document_types' => Schema::hasTable('document_types') ? (int) DB::table('document_types')->count() : 0,
            'announcements' => Schema::hasTable('announcements') ? (int) DB::table('announcements')->count() : 0,
            'notifications' => Schema::hasTable('scholar_notifications')
                ? (int) DB::table('scholar_notifications')->count()
                : 0,
        ];
    }
}
