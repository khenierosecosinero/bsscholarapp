<?php

namespace App\Services;

use App\Models\AcademicSetting;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicSettingsService
{
    public const SEMESTERS = ['1st Semester', '2nd Semester'];

    public const SEMESTER_ALL = 'all';

    public const CACHE_KEY = 'academic_settings.recording';

    public const SESSION_KEY = 'academic_period_view';

    public function current(): AcademicSetting
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached instanceof AcademicSetting) {
            $fresh = AcademicSetting::query()->find($cached->getKey());
            if ($fresh) {
                return $fresh;
            }

            // Stale cache (e.g. after RefreshDatabase rollback or settings wipe).
            Cache::forget(self::CACHE_KEY);
        } elseif ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $match = AcademicSetting::query()
                ->where('year_start', $this->calendarYearStart())
                ->first();
            if ($match) {
                return $match;
            }

            $existing = AcademicSetting::query()->orderByDesc('year_start')->orderByDesc('id')->first();
            if ($existing) {
                return $existing;
            }

            $year = $this->calendarYearStart();

            return AcademicSetting::create([
                'year_start' => $year,
                'year_end' => $year + 1,
                'semester' => $this->calendarSemester(),
                'is_active' => false,
            ]);
        });
    }

    /**
     * Academic year and semester used to filter what the signed-in user sees.
     * A scholar's saved choice is restored after login. It does not change
     * the administrator's academic years or stamped attendance.
     */
    public function selectedPeriod(): AcademicSetting
    {
        $stored = session(self::SESSION_KEY);
        $year = is_array($stored) ? (int) ($stored['year_start'] ?? 0) : 0;
        $semester = is_array($stored) ? (string) ($stored['semester'] ?? '') : '';
        $options = $this->yearOptions();

        if (! isset($options[$year]) || ! in_array($semester, self::SEMESTERS, true)) {
            $saved = $this->savedScholarView();
            if ($saved !== null) {
                $year = $saved['year_start'];
                $semester = $saved['semester'];
                session([
                    self::SESSION_KEY => [
                        'year_start' => $year,
                        'semester' => $semester,
                    ],
                ]);
            } else {
                $current = $this->current();
                $year = (int) $current->year_start;
                $semester = in_array($current->semester, self::SEMESTERS, true)
                    ? $current->semester
                    : self::SEMESTERS[0];
            }
        }

        return new AcademicSetting([
            'year_start' => $year,
            'year_end' => $year + 1,
            'semester' => $semester,
        ]);
    }

    /**
     * @return array{year_start: int, semester: string}|null
     */
    private function savedScholarView(): ?array
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isScholar()) {
            return null;
        }

        $year = (int) $user->academic_year_start;
        $semester = (string) $user->semester;
        if (! isset($this->yearOptions()[$year]) || ! in_array($semester, self::SEMESTERS, true)) {
            return null;
        }

        return [
            'year_start' => $year,
            'semester' => $semester,
        ];
    }

    public function rememberSelectedPeriod(int $yearStart, string $semester): AcademicSetting
    {
        if (! isset($this->yearOptions()[$yearStart]) || ! in_array($semester, self::SEMESTERS, true)) {
            throw ValidationException::withMessages([
                'year_start' => 'Choose an academic year configured by the Administrator.',
            ]);
        }

        session([
            self::SESSION_KEY => [
                'year_start' => $yearStart,
                'semester' => $semester,
            ],
        ]);

        return $this->selectedPeriod();
    }

    public function calendarYearStart(?Carbon $at = null): int
    {
        $at ??= now();

        return (int) $at->month >= 8 ? (int) $at->year : (int) $at->year - 1;
    }

    public function calendarSemester(?Carbon $at = null): string
    {
        $at ??= now();

        return (int) $at->month >= 8 ? '1st Semester' : '2nd Semester';
    }

    public function managedYears()
    {
        $this->current();

        return AcademicSetting::query()
            ->orderByDesc('year_start')
            ->orderByDesc('id')
            ->get();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Current academic period for service hours, dashboards, and new attendance.
     * The administrator's active academic year is the system-wide reference.
     * A scholar's saved year does not replace it, and historical attendance
     * stamps are left on their original academic year.
     */
    public function forUser(User $user): AcademicSetting
    {
        return $this->current();
    }

    public function updateGlobal(array $data, User $user): AcademicSetting
    {
        $validated = $this->validatePeriod($data);
        $setting = AcademicSetting::query()->firstOrCreate(
            ['year_start' => $validated['year_start']],
            [
                'year_end' => $validated['year_end'],
                'semester' => $validated['semester'],
                'is_active' => false,
            ]
        );

        $setting->update([
            'year_end' => $validated['year_end'],
            'semester' => $validated['semester'],
            'updated_by' => $user->id,
        ]);

        return $this->activate($setting, $user);
    }

    public function createYear(string $label, User $user): AcademicSetting
    {
        $period = $this->parseYearLabel($label);
        $this->assertYearAvailable($period['year_start']);

        $hasYears = AcademicSetting::query()->exists();
        $semester = $hasYears
            ? ($this->current()->semester ?: '2nd Semester')
            : '2nd Semester';

        $setting = AcademicSetting::create([
            'year_start' => $period['year_start'],
            'year_end' => $period['year_end'],
            'semester' => $semester,
            'is_active' => false,
            'updated_by' => $user->id,
        ]);

        if (! $hasYears) {
            $this->clearCache();
        }

        return $setting;
    }

    public function updateYear(AcademicSetting $setting, string $label, User $user): AcademicSetting
    {
        $period = $this->parseYearLabel($label);
        $this->assertYearAvailable($period['year_start'], $setting->id);

        $setting->update([
            'year_start' => $period['year_start'],
            'year_end' => $period['year_end'],
            'updated_by' => $user->id,
        ]);

        $this->clearCache();

        return $setting->fresh();
    }

    public function activate(AcademicSetting $setting, User $user): AcademicSetting
    {
        DB::transaction(function () use ($setting, $user) {
            AcademicSetting::query()
                ->where('is_active', true)
                ->whereKeyNot($setting->id)
                ->update(['is_active' => false]);

            $setting->update([
                'is_active' => true,
                'updated_by' => $user->id,
            ]);
        });

        $this->clearCache();
        $this->forgetReportYearSessions();

        return $setting->fresh();
    }

    public function deactivate(AcademicSetting $setting, User $user): AcademicSetting
    {
        if (! $setting->is_active) {
            return $setting;
        }

        $replacement = AcademicSetting::query()
            ->whereKeyNot($setting->id)
            ->orderByDesc('year_start')
            ->first();

        if (! $replacement) {
            throw ValidationException::withMessages([
                'academic_year' => 'Keep at least one academic year active.',
            ]);
        }

        DB::transaction(function () use ($setting, $replacement, $user) {
            $setting->update([
                'is_active' => false,
                'updated_by' => $user->id,
            ]);

            $replacement->update([
                'is_active' => true,
                'updated_by' => $user->id,
            ]);
        });

        $this->clearCache();
        $this->forgetReportYearSessions();

        return $setting->fresh();
    }

    public function deleteYear(AcademicSetting $setting, User $user): void
    {
        if (AcademicSetting::query()->count() <= 1) {
            throw ValidationException::withMessages([
                'academic_year' => 'Keep at least one academic year.',
            ]);
        }

        DB::transaction(function () use ($setting) {
            User::query()
                ->where('academic_year_start', $setting->year_start)
                ->update([
                    'academic_year_start' => null,
                    'semester' => null,
                ]);

            $setting->delete();
        });

        $this->clearCache();
        $this->forgetReportYearSessions();
    }

    /**
     * @return array{
     *     scholars: int,
     *     events: int,
     *     attendances: int,
     *     documents: int,
     *     service_hours: int,
     *     has_records: bool,
     *     warning: string
     * }
     */
    public function usageSummary(AcademicSetting $setting): array
    {
        [$start, $end] = $this->eventBoundsForReport([
            'year_start' => (int) $setting->year_start,
            'semester' => self::SEMESTER_ALL,
        ]);

        $scholars = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->where('academic_year_start', $setting->year_start)
            ->count();

        $events = Event::query()
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$start, $end])
            ->count();

        $attendances = Attendance::query()
            ->where('academic_year_start', $setting->year_start)
            ->count();

        $documents = Document::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $serviceHours = Attendance::query()
            ->where('academic_year_start', $setting->year_start)
            ->where('status', Attendance::STATUS_APPROVED)
            ->where('hours_earned', '>', 0)
            ->count();

        $parts = [];
        if ($scholars > 0) {
            $parts[] = $scholars.' scholar'.($scholars === 1 ? '' : 's');
        }
        if ($events > 0) {
            $parts[] = $events.' event'.($events === 1 ? '' : 's');
        }
        if ($attendances > 0) {
            $parts[] = $attendances.' attendance record'.($attendances === 1 ? '' : 's');
        }
        if ($documents > 0) {
            $parts[] = $documents.' document'.($documents === 1 ? '' : 's');
        }
        if ($serviceHours > 0) {
            $parts[] = $serviceHours.' service-hour record'.($serviceHours === 1 ? '' : 's');
        }

        $warning = $parts === []
            ? ''
            : 'This academic year is already used by '.implode(', ', $parts).'. Related reports still include those records. Those records will stay in the system.';

        return [
            'scholars' => $scholars,
            'events' => $events,
            'attendances' => $attendances,
            'documents' => $documents,
            'service_hours' => $serviceHours,
            'has_records' => $parts !== [],
            'warning' => $warning,
        ];
    }

    /**
     * @param  iterable<AcademicSetting>  $years
     * @return array<int, array<string, mixed>>
     */
    public function usageByYear(iterable $years): array
    {
        $usage = [];

        foreach ($years as $year) {
            $usage[$year->id] = $this->usageSummary($year);
        }

        return $usage;
    }

    /**
     * @return array{year_start: int, year_end: int}
     */
    public function parseYearLabel(string $label): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

        if (! preg_match('/^(?:AY\s*)?(\d{4})\s*[-–—\/]\s*(\d{4})$/u', $normalized, $matches)) {
            throw ValidationException::withMessages([
                'academic_year' => 'Enter the academic year as 2026–2027.',
            ]);
        }

        $yearStart = (int) $matches[1];
        $yearEnd = (int) $matches[2];

        if ($yearStart < 2000 || $yearStart > 2100 || $yearEnd < 2000 || $yearEnd > 2101) {
            throw ValidationException::withMessages([
                'academic_year' => 'Enter a valid academic year between 2000 and 2100.',
            ]);
        }

        if ($yearEnd !== $yearStart + 1) {
            throw ValidationException::withMessages([
                'academic_year' => 'The second year must be one year after the first (for example 2026–2027).',
            ]);
        }

        return [
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
        ];
    }

    private function assertYearAvailable(int $yearStart, ?int $ignoreId = null): void
    {
        $exists = AcademicSetting::query()
            ->where('year_start', $yearStart)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'academic_year' => 'That academic year is already registered.',
            ]);
        }
    }

    private function forgetReportYearSessions(): void
    {
        session()->forget(['admin_report_year', 'staff_report_year']);
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
        $this->current();

        $years = [];
        AcademicSetting::query()
            ->orderBy('year_start')
            ->get(['year_start', 'year_end'])
            ->each(function (AcademicSetting $setting) use (&$years) {
                $years[(int) $setting->year_start] = $setting->periodLabel();
            });

        return $years;
    }

    /**
     * Academic years staff can filter reports by, including years that already
     * have stamped attendance in the given programs.
     *
     * @param  array<int, int>  $programIds
     * @return array<int, string>
     */
    public function reportYearOptions(array $programIds = []): array
    {
        $years = $this->yearOptions();

        Attendance::query()
            ->whereNotNull('academic_year_start')
            ->where('academic_year_start', '>', 0)
            ->when($programIds !== [], function ($query) use ($programIds) {
                $query->whereHas('user', fn ($user) => $user->where('role', User::ROLE_SCHOLAR)
                    ->whereIn('scholarship_program_id', $programIds ?: [0]));
            })
            ->distinct()
            ->pluck('academic_year_start')
            ->each(function ($year) use (&$years) {
                $year = (int) $year;
                if ($year >= 2000) {
                    $years[$year] = $year.'–'.($year + 1);
                }
            });

        ksort($years);

        return $years;
    }

    /**
     * @return array{year_start: int, year_end: int, semester: string, semester_label: string, academic_year: string, label: string}
     */
    public function resolveReportFilter(?int $yearStart, ?string $semester): array
    {
        $current = $this->current();
        $year = ($yearStart !== null && $yearStart >= 2000 && $yearStart <= 2100)
            ? $yearStart
            : (int) $current->year_start;

        $semester = $semester ?: self::SEMESTER_ALL;
        if ($semester !== self::SEMESTER_ALL && ! in_array($semester, self::SEMESTERS, true)) {
            $semester = self::SEMESTER_ALL;
        }

        $semesterLabel = $semester === self::SEMESTER_ALL ? 'All Semesters' : $semester;

        return [
            'year_start' => $year,
            'year_end' => $year + 1,
            'semester' => $semester,
            'semester_label' => $semesterLabel,
            'academic_year' => 'AY '.$year.'–'.($year + 1),
            'label' => $semesterLabel.' · AY '.$year.'–'.($year + 1),
        ];
    }

    public function scopeAttendancesForReport(Builder|Relation $query, array $filter): Builder|Relation
    {
        $query->where('academic_year_start', $filter['year_start']);

        if (($filter['semester'] ?? self::SEMESTER_ALL) !== self::SEMESTER_ALL) {
            $query->where('semester', $filter['semester']);
        }

        return $query;
    }

    /**
     * Filter documents to the selected academic year (and legacy unstamped rows by upload date).
     *
     * @param  array{year_start: int, semester?: string}  $filter
     */
    public function scopeDocumentsForReport(Builder|Relation $query, array $filter): Builder|Relation
    {
        $year = (int) $filter['year_start'];
        [$start, $end] = $this->eventBoundsForReport([
            'year_start' => $year,
            'semester' => self::SEMESTER_ALL,
        ]);

        return $query->where(function ($scoped) use ($year, $start, $end) {
            $scoped->where('academic_year_start', $year)
                ->orWhere(function ($legacy) use ($start, $end) {
                    $legacy->whereNull('academic_year_start')
                        ->where(function ($dates) use ($start, $end) {
                            $dates->whereBetween('uploaded_at', [$start, $end])
                                ->orWhere(function ($created) use ($start, $end) {
                                    $created->whereNull('uploaded_at')
                                        ->whereBetween('created_at', [$start, $end]);
                                });
                        });
                });
        });
    }

    /**
     * Filter events whose start falls inside the selected academic year / semester window.
     *
     * @param  array{year_start: int, semester?: string}  $filter
     */
    public function scopeEventsForReport(Builder|Relation $query, array $filter): Builder|Relation
    {
        [$start, $end] = $this->eventBoundsForReport($filter);

        return $query->whereBetween('starts_at', [$start, $end]);
    }

    /**
     * Report filter for the currently selected academic year.
     * Pass $semester = null to keep the selected semester; pass SEMESTER_ALL for year-wide views.
     *
     * @return array{year_start: int, year_end: int, semester: string, semester_label: string, academic_year: string, label: string}
     */
    public function selectedReportFilter(?string $semester = null): array
    {
        $period = $this->selectedPeriod();

        return $this->resolveReportFilter(
            (int) $period->year_start,
            $semester ?? $period->semester
        );
    }

    /**
     * Admin Portal views the whole academic year (both semesters).
     *
     * @return array{year_start: int, year_end: int, semester: string, semester_label: string, academic_year: string, label: string}
     */
    public function adminYearFilter(): array
    {
        return $this->selectedReportFilter(self::SEMESTER_ALL);
    }

    /**
     * Scholars associated with a viewing academic year:
     * pending accounts, unset start year, 4-year program window, or year-stamped activity.
     */
    public function scopeScholarsForAcademicYear(Builder|Relation $query, int $yearStart): Builder|Relation
    {
        return $query->where(function ($scoped) use ($yearStart) {
            $scoped->where('status', User::STATUS_PENDING)
                ->orWhereNull('program_start_year')
                ->orWhere(function ($window) use ($yearStart) {
                    $window->where('program_start_year', '<=', $yearStart)
                        ->where('program_start_year', '>=', $yearStart - 3);
                })
                ->orWhereHas('attendances', fn ($attendance) => $attendance->where('academic_year_start', $yearStart))
                ->orWhereHas('documents', fn ($document) => $document->where('academic_year_start', $yearStart));
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function eventBoundsForReport(array $filter): array
    {
        $year = (int) $filter['year_start'];

        if (($filter['semester'] ?? self::SEMESTER_ALL) === '1st Semester') {
            return [
                Carbon::create($year, 8, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        if (($filter['semester'] ?? self::SEMESTER_ALL) === '2nd Semester') {
            return [
                Carbon::create($year + 1, 1, 1)->startOfDay(),
                Carbon::create($year + 1, 5, 31)->endOfDay(),
            ];
        }

        return [
            Carbon::create($year, 8, 1)->startOfDay(),
            Carbon::create($year + 1, 7, 31)->endOfDay(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function reportSemesterOptions(): array
    {
        return [
            self::SEMESTER_ALL => 'All Semesters',
            '1st Semester' => '1st Semester',
            '2nd Semester' => '2nd Semester',
        ];
    }

    public function label(AcademicSetting $setting, string $style = 'short'): string
    {
        if ($style === 'long') {
            return 'AY '.$setting->periodLabel();
        }

        return $setting->periodLabel();
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

    /**
     * Stamp a new attendance row with the administrator's current period.
     * Rows that already have an academic year keep that stamp.
     */
    public function preservedAttendanceStamp(?Attendance $attendance = null): array
    {
        if ($attendance?->academic_year_start) {
            return [
                'academic_year_start' => (int) $attendance->academic_year_start,
                'academic_year_end' => (int) ($attendance->academic_year_end ?: ((int) $attendance->academic_year_start + 1)),
                'semester' => $attendance->semester,
            ];
        }

        return $this->attendanceStamp($this->current());
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
        $setting = $this->selectedPeriod();

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
            'is_global' => true,
        ];
    }
}
