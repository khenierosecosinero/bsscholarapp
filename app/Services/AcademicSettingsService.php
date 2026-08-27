<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

class AcademicSettingsService
{
    public const SEMESTERS = ['1st Semester', '2nd Semester'];

    public const CACHE_KEY = 'academic_settings.current';

    public function current(): AcademicSetting
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return AcademicSetting::query()->first()
                ?? AcademicSetting::create([
                    'year_start' => (int) now()->year,
                    'year_end' => (int) now()->year + 1,
                    'semester' => '2nd Semester',
                ]);
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function forUser(User $user): AcademicSetting
    {
        if ($user->academic_year_start && $user->semester) {
            $setting = new AcademicSetting([
                'year_start' => $user->academic_year_start,
                'year_end' => $user->academic_year_start + 1,
                'semester' => $user->semester,
            ]);

            return $setting;
        }

        return $this->current();
    }

    public function updateGlobal(array $data, User $user): AcademicSetting
    {
        $validated = $this->validatePeriod($data);

        $setting = $this->current();
        $setting->update(array_merge($validated, ['updated_by' => $user->id]));
        $this->clearCache();

        return $setting->fresh();
    }

    public function updateUserPreference(User $user, array $data): User
    {
        $validated = $this->validatePeriod($data);

        $user->update([
            'academic_year_start' => $validated['year_start'],
            'semester' => $validated['semester'],
        ]);

        return $user->fresh();
    }

    public function validatePeriod(array $data): array
    {
        $yearStart = (int) $data['year_start'];
        $semester = $data['semester'];

        if (! in_array($semester, self::SEMESTERS, true)) {
            throw new \InvalidArgumentException('Invalid semester selected.');
        }

        return [
            'year_start' => $yearStart,
            'year_end' => $yearStart + 1,
            'semester' => $semester,
        ];
    }

    public function yearOptions(int $range = 5): array
    {
        $current = (int) now()->year;
        $years = [];

        for ($y = $current - 2; $y <= $current + $range; $y++) {
            $years[$y] = "{$y}–".($y + 1);
        }

        return $years;
    }

    public function label(AcademicSetting $setting, string $style = 'short'): string
    {
        if ($style === 'long') {
            return 'AY '.$setting->year_start.' - '.$setting->year_end;
        }

        return $setting->year_start.'-'.$setting->year_end;
    }

    public function semesterBounds(AcademicSetting $setting): array
    {
        if ($setting->semester === '1st Semester') {
            return [
                Carbon::create($setting->year_start, 8, 1)->startOfDay(),
                Carbon::create($setting->year_start, 12, 31)->endOfDay(),
            ];
        }

        return [
            Carbon::create($setting->year_end, 1, 1)->startOfDay(),
            Carbon::create($setting->year_end, 5, 31)->endOfDay(),
        ];
    }

    public function attendanceStamp(AcademicSetting $setting): array
    {
        return [
            'academic_year_start' => $setting->year_start,
            'academic_year_end' => $setting->year_end,
            'semester' => $setting->semester,
        ];
    }

    public function scopeAttendancesForPeriod(Builder|Relation $query, AcademicSetting $setting): Builder|Relation
    {
        return $query
            ->where('academic_year_start', $setting->year_start)
            ->where('academic_year_end', $setting->year_end)
            ->where('semester', $setting->semester);
    }

    public function backfillAttendances(?AcademicSetting $setting = null): int
    {
        $setting ??= $this->current();
        $stamp = $this->attendanceStamp($setting);

        return Attendance::query()
            ->whereNull('academic_year_start')
            ->update($stamp);
    }

    public function infoForUser(User $user, array $hourStats): array
    {
        $setting = $this->forUser($user);

        if ($hourStats['approved'] >= $hourStats['required']) {
            $status = 'Completed';
            $statusSlug = 'completed';
        } elseif ($hourStats['approved'] > 0) {
            $status = 'In Progress';
            $statusSlug = 'in-progress';
        } else {
            $status = 'Incomplete';
            $statusSlug = 'incomplete';
        }

        return [
            'year_start' => $setting->year_start,
            'year_end' => $setting->year_end,
            'academic_year' => $this->label($setting, 'long'),
            'academic_year_short' => $this->label($setting, 'short'),
            'semester' => $setting->semester,
            'status' => $status,
            'status_slug' => $statusSlug,
            'is_global' => ! ($user->academic_year_start && $user->semester),
        ];
    }
}
