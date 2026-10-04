<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClubSchool;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\PercentShare;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StaffDashboardService
{
    public function __construct(
        private AcademicSettingsService $academic,
        private ScholarService $scholar,
    ) {}

    public function programIds(User $staff): array
    {
        return $staff->managedLocationIds();
    }

    public function layoutPayload(User $staff, string $active, string $title, string $subtitle = '', ?string $breadcrumb = null): array
    {
        $staff->loadMissing('scholarshipProgram', 'scholarshipClub');
        $program = $staff->scholarshipProgram;
        $programIds = $this->programIds($staff);

        return [
            'staff' => $staff,
            'program' => $program,
            'programIds' => $programIds,
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle ?: ($staff->scholarshipClubName() !== 'Unassigned' ? 'Managing '.$staff->scholarshipClubLabel().'.' : 'Manage your assigned Scholarship Club.'),
            'breadcrumb' => $breadcrumb ?? $title,
            'pendingApprovalsCount' => $this->pendingApprovalsCount($programIds, $staff),
        ];
    }

    public function forgetPendingApprovalsCache(array $programIds, ?User $staff = null): void
    {
        Cache::forget('staff.pending_approvals.'.$this->programCacheKey($programIds, $staff));
    }

    public function approvalRequestStats(array $programIds, ?User $staff = null): array
    {
        $counts = $this->scholarStatusCounts($programIds, $staff);

        return [
            'total' => array_sum($counts),
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
        ];
    }

    public function pendingApprovalsCount(array $programIds, ?User $staff = null): int
    {
        return Cache::remember(
            'staff.pending_approvals.'.$this->programCacheKey($programIds, $staff),
            20,
            fn () => $this->scholarsQuery($programIds, $staff)->where('status', 'pending')->count()
        );
    }

    public function scholarPageStats(array $programIds, ?User $staff = null, ?array $clubIds = null): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds, $staff, $clubIds);

        return [
            'total_scholars' => array_sum($statusCounts),
            'active_scholars' => $statusCounts['approved'] ?? 0,
            'pending_scholars' => $statusCounts['pending'] ?? 0,
            'pending_documents' => Document::query()
                ->where('status', 'pending')
                ->whereHas('user', function ($q) use ($programIds, $staff, $clubIds) {
                    $q->where('role', User::ROLE_SCHOLAR)
                        ->whereIn('scholarship_program_id', $programIds ?: [0]);
                    $this->constrainClubs($q, $staff, $clubIds);
                })
                ->count(),
        ];
    }

    public function scholarsQuery(array $programIds, ?User $staff = null, ?array $clubIds = null)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

        return $this->constrainClubs($query, $staff, $clubIds);
    }

    public function reportScholarsQuery(array $programIds, ?User $staff = null, ?array $clubIds = null)
    {
        return $this->scholarsQuery($programIds, $staff, $clubIds)
            ->where('status', User::STATUS_APPROVED);
    }

    public function dashboardStats(array $programIds, ?User $staff = null, ?array $clubIds = null): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds, $staff, $clubIds);
        $totalScholars = array_sum($statusCounts);

        $events = Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->visibleToStaff($staff);

        $pendingAttendances = Attendance::query()
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->count();

        $pendingDocuments = Document::query()
            ->where('status', 'pending')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->count();

        $totalServiceHours = Attendance::query()
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->sum('hours_earned');

        return [
            'total_scholars' => $totalScholars,
            'active_scholars' => $statusCounts['approved'] ?? 0,
            'pending_scholars' => $statusCounts['pending'] ?? 0,
            'rejected_scholars' => $statusCounts['rejected'] ?? 0,
            'total_events' => (clone $events)->count(),
            'upcoming_events' => (clone $events)->where('starts_at', '>=', now())->count(),
            'pending_attendances' => $pendingAttendances,
            'pending_documents' => $pendingDocuments,
            'total_service_hours' => number_format((float) $totalServiceHours, 2),
        ];
    }

    public function pendingApprovals(array $programIds, int $limit = 5): Collection
    {
        return $this->scholarsQuery($programIds)
            ->with('scholarshipProgram')
            ->where('status', 'pending')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function latestScholars(array $programIds, int $limit = 5): Collection
    {
        return $this->scholarsQuery($programIds)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function recentActivities(array $programIds, int $limit = 5): Collection
    {
        return UserActivity::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->latest()
            ->limit($limit)
            ->with('user')
            ->get();
    }

    public function upcomingEvents(array $programIds, int $limit = 3, ?User $staff = null): Collection
    {
        return Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->visibleToStaff($staff)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    public function attendanceBreakdown(array $programIds, ?array $filter = null, ?User $staff = null, ?array $clubIds = null, ?array $schoolFilter = null): array
    {
        $base = $this->attendanceQuery($programIds, $filter, $staff, $clubIds, $schoolFilter);

        $approved = (clone $base)->where('status', Attendance::STATUS_APPROVED)->whereNotNull('check_in')->count();
        $pending = (clone $base)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->count();
        $rejected = (clone $base)->where('status', Attendance::STATUS_REJECTED)->count();
        $failedCheckIn = (clone $base)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count();
        $total = max(1, $approved + $pending + $rejected + $failedCheckIn);

        return compact('approved', 'pending', 'rejected', 'failedCheckIn', 'total');
    }

    /**
     * @param  array<int, int>  $programIds
     * @return array{
     *     reportFilter: array<string, mixed>,
     *     reportYearOptions: array<int, string>,
     *     reportSemesterOptions: array<string, string>,
     *     schoolFilter: array<string, mixed>,
     *     reportSchoolOptions: array<int, array{key: string, label: string}>,
     *     selectedSchoolKey: string,
     *     selectedSchoolLabel: string
     * }
     */
    public function reportContext(Request $request, array $programIds, ?User $staff = null): array
    {
        $yearRaw = $request->query('year', session('staff_report_year'));
        $semesterRaw = $request->query('semester', session('staff_report_semester', AcademicSettingsService::SEMESTER_ALL));
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        $semester = is_string($semesterRaw) ? $semesterRaw : AcademicSettingsService::SEMESTER_ALL;

        $filter = $this->academic->resolveReportFilter($year, $semester);
        $school = $this->resolveSchoolFilter($request, $programIds, $staff);
        session([
            'staff_report_year' => $filter['year_start'],
            'staff_report_semester' => $filter['semester'],
            'staff_report_school' => $school['key'],
        ]);

        return [
            'reportFilter' => $filter,
            'reportYearOptions' => $this->academic->reportYearOptions($programIds),
            'reportSemesterOptions' => $this->academic->reportSemesterOptions(),
            'schoolFilter' => $school,
            'reportSchoolOptions' => $school['options'],
            'selectedSchoolKey' => $school['key'],
            'selectedSchoolLabel' => $school['label'],
        ];
    }

    public function hoursOverview(array $programIds): array
    {
        $base = $this->attendanceQuery($programIds);

        $approvedHours = (float) (clone $base)->where('status', Attendance::STATUS_APPROVED)->whereNotNull('check_in')->sum('hours_earned');
        $pendingHours = (float) (clone $base)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->sum('hours_earned');
        $rejectedCount = (clone $base)->where('status', Attendance::STATUS_REJECTED)->count();
        $failedCount = (clone $base)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count();

        return [
            'approved_hours' => $approvedHours,
            'pending_hours' => $pendingHours,
            'rejected_count' => $rejectedCount,
            'failed_count' => $failedCount,
        ];
    }

    public function serviceHoursReport(array $programIds, ?array $filter = null, ?User $staff = null, ?array $clubIds = null, ?array $schoolFilter = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $required = (float) $this->scholar->periodHourStatsFromRecords([], $filter)['required'];
        $allScholars = $this->reportScholarsQuery($programIds, $staff, $clubIds)
            ->with(['scholarshipProgram', 'scholarshipClub', 'scholarshipClubSchool'])
            ->orderBy('full_name')
            ->get();
        $scholars = $this->scholarsForSchool($allScholars, $schoolFilter);

        $allHourRows = $this->attendanceQuery($programIds, $filter, $staff, $clubIds)
            ->whereNotNull('check_in')
            ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
            ->get(['user_id', 'academic_year_start', 'semester', 'status', 'hours_earned'])
            ->groupBy('user_id');
        $hourRows = $allHourRows;
        $periodQuery = $this->attendanceQuery($programIds, $filter, $staff, $clubIds, $schoolFilter);

        $rows = $scholars->map(function (User $scholar) use ($hourRows, $filter, $required) {
            $stats = $this->scholar->periodHourStatsFromRecords(
                $hourRows->get($scholar->id, collect()),
                $filter
            );
            $approved = (float) $stats['approved'];
            $pending = (float) $stats['pending'];
            $remaining = (float) $stats['remaining'];
            $status = $approved >= $required
                ? 'Completed'
                : ($approved > 0 || $pending > 0 ? 'In Progress' : 'Not Started');

            return [
                'scholar' => $scholar,
                'approved' => $approved,
                'pending' => $pending,
                'remaining' => $remaining,
                'status' => $status,
            ];
        });

        $overview = [
            'approved_hours' => round((float) $rows->sum('approved'), 2),
            'pending_hours' => (float) (clone $periodQuery)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->sum('hours_earned'),
            'rejected_count' => (clone $periodQuery)->where('status', Attendance::STATUS_REJECTED)->count(),
            'failed_count' => (clone $periodQuery)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count(),
        ];

        $completed = $rows->where('status', 'Completed')->count();
        $inProgress = $rows->where('status', 'In Progress')->count();
        $notStarted = $rows->where('status', 'Not Started')->count();

        $allRecent = $this->attendanceQuery($programIds, $filter, $staff, $clubIds)
            ->with(['user.scholarshipClubSchool', 'event'])
            ->whereNotNull('check_in')
            ->latest()
            ->get();
        $scopedIds = $scholars->pluck('id')->all();
        $recent = $allRecent->filter(fn ($attendance) => in_array((int) $attendance->user_id, $scopedIds, true))->take(20)->values();

        $allRows = $this->hourRowsForScholars($allScholars, $allHourRows, $filter, $required);
        $schools = $this->schoolGroupsFromHourRows($allScholars, $allRows, $allRecent, $staff, $schoolFilter);

        return [
            'period' => $filter,
            'required' => $required,
            'overview' => $overview,
            'rows' => $rows,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'recent' => $recent,
            'schools' => $schools,
            'show_school_breakdown' => $this->isAllSchools($schoolFilter),
        ];
    }

    public function attendanceReport(array $programIds, ?array $filter = null, ?User $staff = null, ?array $schoolFilter = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $allScholars = $this->reportScholarsQuery($programIds, $staff)
            ->with('scholarshipClubSchool')
            ->orderBy('full_name')
            ->get();
        $scholars = $this->scholarsForSchool($allScholars, $schoolFilter);
        $breakdown = $this->attendanceBreakdown($programIds, $filter, $staff, null, $schoolFilter);
        $checkedIn = $breakdown['approved'] + $breakdown['pending'] + $breakdown['rejected'];
        $allRecords = $this->attendanceQuery($programIds, $filter, $staff)
            ->with(['user.scholarshipClubSchool', 'event'])
            ->latest()
            ->get();
        $scopedIds = $scholars->pluck('id')->all();
        $records = $allRecords->filter(fn ($attendance) => in_array((int) $attendance->user_id, $scopedIds, true))->take(30)->values();

        return [
            'period' => $filter,
            'breakdown' => $breakdown,
            'checked_in' => $checkedIn,
            'rate' => $breakdown['total'] > 0
                ? round(($checkedIn / max(1, $checkedIn + $breakdown['failedCheckIn'])) * 100, 1)
                : 0,
            'records' => $records,
            'schools' => $this->schoolGroupsFromAttendance($allScholars, $allRecords, $staff, $schoolFilter),
            'show_school_breakdown' => $this->isAllSchools($schoolFilter),
        ];
    }

    public function participationCounts(array $programIds, ?array $filter = null, ?User $staff = null, ?array $schoolFilter = null): array
    {
        $registrations = EventRegistration::query()
            ->whereHas('user', function ($q) use ($programIds, $staff, $schoolFilter) {
                $this->scopeReportUsers($q, $programIds, $staff, null, $schoolFilter);
            });

        if ($filter) {
            [$start, $end] = $this->academic->eventBoundsForReport($filter);
            $registrations->whereHas('event', fn ($event) => $event
                ->whereIn('scholarship_program_id', $programIds ?: [0])
                ->whereBetween('starts_at', [$start, $end]));
        }

        return [
            'registered' => (clone $registrations)->count(),
            'failed' => (clone $registrations)->where('status', EventRegistration::STATUS_FAILED_CHECK_IN)->count(),
            'confirmed' => (clone $registrations)->where('status', EventRegistration::STATUS_CONFIRMED)->count(),
            'participated' => $this->attendanceQuery($programIds, $filter, $staff, null, $schoolFilter)
                ->where('status', Attendance::STATUS_APPROVED)
                ->whereNotNull('check_in')
                ->count(),
            'pending' => $this->attendanceQuery($programIds, $filter, $staff, null, $schoolFilter)
                ->where('status', Attendance::STATUS_PENDING)
                ->whereNotNull('check_in')
                ->count(),
        ];
    }

    public function participationReport(array $programIds, ?array $filter = null, ?User $staff = null, ?array $schoolFilter = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $allScholars = $this->reportScholarsQuery($programIds, $staff)
            ->with('scholarshipClubSchool')
            ->orderBy('full_name')
            ->get();
        $scholars = $this->scholarsForSchool($allScholars, $schoolFilter);
        $counts = $this->participationCounts($programIds, $filter, $staff, $schoolFilter);
        [$start, $end] = $this->academic->eventBoundsForReport($filter);

        $events = Event::query()
            ->with([
                'scholarshipProgram',
                'registrations:id,event_id,user_id,status',
                'attendances:id,event_id,user_id,status,check_in',
            ])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->where(function ($query) use ($start, $end, $filter) {
                $query->whereBetween('starts_at', [$start, $end])
                    ->orWhereHas('attendances', fn ($attendance) => $this->academic->scopeAttendancesForReport($attendance, $filter));
            })
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        $scopedIds = $scholars->pluck('id')->map(fn ($id) => (int) $id)->all();
        $eventRows = $this->participationEventRows($events, $scopedIds);

        return array_merge($counts, [
            'events' => $eventRows,
            'period' => $filter,
            'schools' => $this->schoolGroupsFromParticipation($allScholars, $events, $filter, $programIds, $staff, $schoolFilter),
            'show_school_breakdown' => $this->isAllSchools($schoolFilter),
        ]);
    }

    public function completionCounts(array $programIds, ?array $clubIds = null): array
    {
        $period = $this->academic->current();
        $required = ScholarService::REQUIRED_HOURS;
        $total = $this->scholarsQuery($programIds, null, $clubIds)->count();

        $hourRows = $this->attendanceQuery($programIds)
            ->whereNotNull('check_in')
            ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
            ->get(['user_id', 'academic_year_start', 'semester', 'status', 'hours_earned'])
            ->groupBy('user_id');

        $completed = 0;
        $inProgress = 0;
        $approvedHours = 0.0;

        foreach ($hourRows as $records) {
            $stats = $this->scholar->serviceHourStatsFromRecords($records, $period);
            $approved = (float) $stats['approved'];
            $pending = (float) $stats['pending'];
            $approvedHours += $approved;

            if ($approved >= $required) {
                $completed++;
            } elseif ($approved > 0 || $pending > 0 || (float) ($stats['carried_in'] ?? 0) > 0) {
                $inProgress++;
            }
        }

        $notStarted = max(0, $total - $completed - $inProgress);

        return [
            'period' => $period,
            'required' => $required,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'approved_hours' => round($approvedHours, 2),
            'completed_pct' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public function completionReport(array $programIds, ?array $filter = null, ?User $staff = null, ?array $schoolFilter = null): array
    {
        $report = $this->serviceHoursReport($programIds, $filter, $staff, null, $schoolFilter);
        $total = max(1, $report['rows']->count());

        return [
            'period' => $report['period'],
            'required' => $report['required'],
            'completed' => $report['completed'],
            'in_progress' => $report['in_progress'],
            'not_started' => $report['not_started'],
            'completed_pct' => round(($report['completed'] / $total) * 100, 1),
            'rows' => $report['rows'],
            'schools' => $report['schools'],
            'show_school_breakdown' => $report['show_school_breakdown'],
        ];
    }

    private function scholarStatusCounts(array $programIds, ?User $staff = null, ?array $clubIds = null): array
    {
        return $this->scholarsQuery($programIds, $staff, $clubIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function constrainClubs($query, ?User $staff, ?array $clubIds)
    {
        if ($staff?->scholarship_club_id) {
            $query->where('scholarship_club_id', $staff->scholarship_club_id);
        } elseif ($clubIds !== null) {
            $query->whereIn('scholarship_club_id', $clubIds ?: [0]);
        }

        return $query;
    }

    private function programCacheKey(array $programIds, ?User $staff = null): string
    {
        $programIds = array_values(array_unique(array_map('intval', $programIds)));
        sort($programIds);

        return md5(implode(',', $programIds).'|club:'.($staff?->scholarship_club_id ?? '0'));
    }

    private function defaultReportFilter(): array
    {
        $current = $this->academic->current();

        return $this->academic->resolveReportFilter((int) $current->year_start, $current->semester);
    }

    private function attendanceQuery(array $programIds, ?array $filter = null, ?User $staff = null, ?array $clubIds = null, ?array $schoolFilter = null)
    {
        $query = Attendance::query()
            ->whereHas('user', function ($q) use ($programIds, $staff, $clubIds, $schoolFilter) {
                $this->scopeReportUsers($q, $programIds, $staff, $clubIds, $schoolFilter);
            });

        if ($filter) {
            $this->academic->scopeAttendancesForReport($query, $filter);
        }

        return $query;
    }

    private function scopeReportUsers($query, array $programIds, ?User $staff = null, ?array $clubIds = null, ?array $schoolFilter = null)
    {
        $query->where('role', User::ROLE_SCHOLAR)
            ->where('status', User::STATUS_APPROVED)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

        $this->constrainClubs($query, $staff, $clubIds);
        $this->constrainSchool($query, $schoolFilter);

        return $query;
    }

    private function constrainSchool($query, ?array $schoolFilter)
    {
        if ($this->isAllSchools($schoolFilter)) {
            return $query;
        }

        $type = $schoolFilter['type'] ?? 'all';

        if ($type === 'unassigned') {
            return $query->where(function ($scoped) {
                $scoped->whereNull('scholarship_club_school_id')
                    ->where(function ($inner) {
                        $inner->whereNull('school_university')
                            ->orWhere('school_university', '');
                    });
            });
        }

        if ($type === 'id') {
            return $query->where('scholarship_club_school_id', (int) $schoolFilter['value']);
        }

        if ($type === 'name') {
            return $query->whereRaw('LOWER(TRIM(school_university)) = ?', [mb_strtolower(trim((string) $schoolFilter['value']))]);
        }

        return $query;
    }

    private function isAllSchools(?array $schoolFilter): bool
    {
        return ! $schoolFilter || ($schoolFilter['type'] ?? 'all') === 'all';
    }

    /**
     * @return array{key: string, type: string, value: mixed, label: string, options: list<array{key: string, label: string}>}
     */
    private function resolveSchoolFilter(Request $request, array $programIds, ?User $staff): array
    {
        $options = $this->reportSchoolOptions($programIds, $staff);
        $allowed = collect($options)->pluck('key')->all();
        $raw = (string) $request->query('school', session('staff_report_school', 'all'));
        $key = in_array($raw, $allowed, true) ? $raw : 'all';
        $selected = collect($options)->firstWhere('key', $key) ?? $options[0];

        return [
            'key' => $selected['key'],
            'type' => $this->schoolFilterType($selected['key']),
            'value' => $this->schoolFilterValue($selected['key']),
            'label' => $selected['label'],
            'options' => $options,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function reportSchoolOptions(array $programIds, ?User $staff): array
    {
        $options = [['key' => 'all', 'label' => 'All Schools/Universities']];
        $seen = [];

        if ($staff?->scholarship_club_id) {
            foreach (ScholarshipClubSchool::query()->where('scholarship_club_id', $staff->scholarship_club_id)->orderBy('name')->get() as $school) {
                $options[] = ['key' => 'id:'.$school->id, 'label' => $school->name];
                $seen[mb_strtolower($school->name)] = true;
            }
        }

        $scholars = $this->scholarsQuery($programIds, $staff)
            ->with('scholarshipClubSchool')
            ->get(['id', 'school_university', 'scholarship_club_school_id']);

        foreach ($scholars as $scholar) {
            $name = $scholar->registeredSchoolName();
            $normalized = mb_strtolower($name);

            if ($name === 'Unassigned') {
                continue;
            }

            if (isset($seen[$normalized]) || $scholar->scholarship_club_school_id) {
                $seen[$normalized] = true;

                continue;
            }

            $seen[$normalized] = true;
            $options[] = ['key' => 'name:'.$name, 'label' => $name];
        }

        if ($scholars->contains(fn (User $scholar) => $scholar->registeredSchoolName() === 'Unassigned')) {
            $options[] = ['key' => 'unassigned', 'label' => 'Unassigned'];
        }

        return $options;
    }

    private function schoolFilterType(string $key): string
    {
        if ($key === 'all') {
            return 'all';
        }

        if ($key === 'unassigned') {
            return 'unassigned';
        }

        return str_starts_with($key, 'id:') ? 'id' : 'name';
    }

    private function schoolFilterValue(string $key): mixed
    {
        if (str_starts_with($key, 'id:')) {
            return (int) substr($key, 3);
        }

        if (str_starts_with($key, 'name:')) {
            return substr($key, 5);
        }

        return null;
    }

    private function scholarsForSchool(Collection $scholars, ?array $schoolFilter): Collection
    {
        if ($this->isAllSchools($schoolFilter)) {
            return $scholars;
        }

        $type = $schoolFilter['type'] ?? 'all';
        $value = $schoolFilter['value'] ?? null;

        return $scholars->filter(function (User $scholar) use ($type, $value) {
            if ($type === 'unassigned') {
                return $scholar->registeredSchoolName() === 'Unassigned';
            }

            if ($type === 'id') {
                return (int) $scholar->scholarship_club_school_id === (int) $value;
            }

            if ($type === 'name') {
                return mb_strtolower($scholar->registeredSchoolName()) === mb_strtolower((string) $value);
            }

            return true;
        })->values();
    }

    private function hourRowsForScholars(Collection $scholars, Collection $hourRows, array $filter, float $required): Collection
    {
        return $scholars->map(function (User $scholar) use ($hourRows, $filter, $required) {
            $stats = $this->scholar->periodHourStatsFromRecords(
                $hourRows->get($scholar->id, collect()),
                $filter
            );
            $approved = (float) $stats['approved'];
            $pending = (float) $stats['pending'];
            $remaining = (float) $stats['remaining'];

            return [
                'scholar' => $scholar,
                'approved' => $approved,
                'pending' => $pending,
                'remaining' => $remaining,
                'status' => $approved >= $required
                    ? 'Completed'
                    : ($approved > 0 || $pending > 0 ? 'In Progress' : 'Not Started'),
            ];
        });
    }

    private function schoolBuckets(Collection $scholars, ?User $staff): Collection
    {
        $buckets = collect();

        if ($staff?->scholarship_club_id) {
            foreach (ScholarshipClubSchool::query()->where('scholarship_club_id', $staff->scholarship_club_id)->orderBy('name')->get() as $school) {
                $buckets[$school->name] = collect();
            }
        }

        foreach ($scholars as $scholar) {
            $name = $scholar->registeredSchoolName();
            $current = $buckets->get($name, collect());
            $buckets[$name] = $current->push($scholar);
        }

        return $buckets;
    }

    private function schoolSharePercents(Collection $buckets): array
    {
        return PercentShare::allocate(
            $buckets->map(fn (Collection $group) => $group->count())->all()
        );
    }

    private function schoolGroupsFromHourRows(Collection $scholars, Collection $rows, Collection $recent, ?User $staff, ?array $schoolFilter): array
    {
        $rowMap = $rows->keyBy(fn (array $row) => $row['scholar']->id);
        $buckets = $this->schoolBuckets($scholars, $staff);
        $percents = $this->schoolSharePercents($buckets);

        return $this->visibleSchoolNames($buckets, $schoolFilter)->map(function (string $name) use ($buckets, $percents, $rowMap, $recent) {
            $schoolScholars = $buckets->get($name, collect());
            $schoolRows = $schoolScholars->map(fn (User $scholar) => $rowMap->get($scholar->id))->filter()->values();
            $ids = $schoolScholars->pluck('id')->all();

            return [
                'name' => $name,
                'percent' => $percents[$name] ?? 0,
                'total' => $schoolScholars->count(),
                'completed' => $schoolRows->where('status', 'Completed')->count(),
                'in_progress' => $schoolRows->where('status', 'In Progress')->count(),
                'not_started' => $schoolRows->where('status', 'Not Started')->count(),
                'approved' => round((float) $schoolRows->sum('approved'), 2),
                'pending' => round((float) $schoolRows->sum('pending'), 2),
                'remaining' => round((float) $schoolRows->sum('remaining'), 2),
                'rows' => $schoolRows,
                'recent' => $recent->filter(fn ($attendance) => in_array((int) $attendance->user_id, $ids, true))->take(20)->values(),
            ];
        })->values()->all();
    }

    private function schoolGroupsFromAttendance(Collection $scholars, Collection $records, ?User $staff, ?array $schoolFilter): array
    {
        $buckets = $this->schoolBuckets($scholars, $staff);
        $percents = $this->schoolSharePercents($buckets);

        return $this->visibleSchoolNames($buckets, $schoolFilter)->map(function (string $name) use ($buckets, $percents, $records) {
            $schoolScholars = $buckets->get($name, collect());
            $ids = $schoolScholars->pluck('id')->all();
            $schoolRecords = $records->filter(fn ($attendance) => in_array((int) $attendance->user_id, $ids, true))->values();
            $approved = $schoolRecords->where('status', Attendance::STATUS_APPROVED)->where(fn ($row) => $row->check_in !== null)->count();
            $pending = $schoolRecords->where('status', Attendance::STATUS_PENDING)->where(fn ($row) => $row->check_in !== null)->count();
            $rejected = $schoolRecords->where('status', Attendance::STATUS_REJECTED)->count();
            $checkedIn = $approved + $pending + $rejected;
            $recordsByUser = $schoolRecords->groupBy('user_id');
            $present = 0;
            $failedScholars = 0;
            $absent = 0;

            foreach ($schoolScholars as $scholar) {
                $recs = $recordsByUser->get($scholar->id, collect());
                if ($recs->contains(fn ($row) => in_array($row->status, [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING], true) && $row->check_in !== null)) {
                    $present++;
                } elseif ($recs->contains(fn ($row) => $row->status === Attendance::STATUS_FAILED_CHECK_IN)) {
                    $failedScholars++;
                } else {
                    $absent++;
                }
            }

            return [
                'name' => $name,
                'percent' => $percents[$name] ?? 0,
                'total' => $schoolScholars->count(),
                'present' => $present,
                'absent' => $absent,
                'completed' => $present,
                'in_progress' => $pending,
                'not_started' => $absent,
                'approved' => $approved,
                'pending' => $pending,
                'rejected' => $rejected,
                'failedCheckIn' => $failedScholars,
                'checked_in' => $checkedIn,
                'rate' => $schoolScholars->count() > 0
                    ? round(($present / $schoolScholars->count()) * 100, 1)
                    : 0,
            ];
        })->values()->all();
    }

    private function schoolGroupsFromParticipation(
        Collection $scholars,
        Collection $events,
        array $filter,
        array $programIds,
        ?User $staff,
        ?array $schoolFilter
    ): array {
        $buckets = $this->schoolBuckets($scholars, $staff);
        $percents = $this->schoolSharePercents($buckets);

        return $this->visibleSchoolNames($buckets, $schoolFilter)->map(function (string $name) use ($buckets, $percents, $events, $filter, $programIds, $staff) {
            $schoolScholars = $buckets->get($name, collect());
            $ids = $schoolScholars->pluck('id')->map(fn ($id) => (int) $id)->all();
            $first = $schoolScholars->first();
            $scopedFilter = $name === 'Unassigned'
                ? ['type' => 'unassigned', 'value' => null, 'key' => 'unassigned']
                : ($first?->scholarship_club_school_id
                    ? ['type' => 'id', 'value' => $first->scholarship_club_school_id, 'key' => 'id:'.$first->scholarship_club_school_id]
                    : ['type' => 'name', 'value' => $name, 'key' => 'name:'.$name]);
            $counts = $this->participationCounts($programIds, $filter, $staff, $scopedFilter);
            $participatedIds = $this->attendanceQuery($programIds, $filter, $staff, null, $scopedFilter)
                ->where('status', Attendance::STATUS_APPROVED)
                ->whereNotNull('check_in')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
            $inProgressIds = $this->attendanceQuery($programIds, $filter, $staff, null, $scopedFilter)
                ->where('status', Attendance::STATUS_PENDING)
                ->whereNotNull('check_in')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
            $registeredIds = EventRegistration::query()
                ->whereIn('user_id', $ids ?: [0])
                ->whereHas('event', function ($event) use ($programIds, $filter) {
                    [$start, $end] = $this->academic->eventBoundsForReport($filter);
                    $event->whereIn('scholarship_program_id', $programIds ?: [0])
                        ->whereBetween('starts_at', [$start, $end]);
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $participated = 0;
            $inProgress = 0;
            $notStarted = 0;

            foreach ($ids as $scholarId) {
                if (in_array($scholarId, $participatedIds, true)) {
                    $participated++;
                } elseif (in_array($scholarId, $inProgressIds, true) || in_array($scholarId, $registeredIds, true)) {
                    $inProgress++;
                } else {
                    $notStarted++;
                }
            }

            return [
                'name' => $name,
                'percent' => $percents[$name] ?? 0,
                'total' => $schoolScholars->count(),
                'completed' => $participated,
                'in_progress' => $inProgress,
                'not_started' => $notStarted,
                'registered' => $counts['registered'],
                'participated' => $participated,
                'pending' => $inProgress,
                'failed' => $counts['failed'],
                'confirmed' => $counts['confirmed'],
            ];
        })->values()->all();
    }

    private function participationEventRows(Collection $events, array $scholarIds): Collection
    {
        $idSet = array_flip($scholarIds);

        return $events->map(function (Event $event) use ($idSet) {
            $registrations = $event->registrations->filter(fn ($row) => isset($idSet[(int) $row->user_id]));
            $attendances = $event->attendances->filter(fn ($row) => isset($idSet[(int) $row->user_id]));

            return [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at,
                'service_hours' => $event->service_hours,
                'registrations_count' => $registrations->count(),
                'checked_in_count' => $attendances->where(fn ($row) => $row->check_in !== null)->count(),
                'approved_count' => $attendances->where('status', Attendance::STATUS_APPROVED)->count(),
            ];
        })->values();
    }

    private function visibleSchoolNames(Collection $buckets, ?array $schoolFilter): Collection
    {
        $names = $buckets->keys()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        if ($this->isAllSchools($schoolFilter)) {
            return $names;
        }

        $match = $schoolFilter['label'] ?? null;
        if ($schoolFilter['type'] === 'unassigned') {
            $match = 'Unassigned';
        } elseif ($schoolFilter['type'] === 'name') {
            $match = (string) $schoolFilter['value'];
        } elseif ($schoolFilter['type'] === 'id') {
            $first = $buckets->first(function (Collection $group) use ($schoolFilter) {
                return $group->contains(fn (User $scholar) => (int) $scholar->scholarship_club_school_id === (int) $schoolFilter['value']);
            });
            $match = $first?->first()?->registeredSchoolName();
            if (! $match) {
                $school = ScholarshipClubSchool::query()->find($schoolFilter['value']);
                $match = $school?->name;
            }
        }

        return $names->filter(fn (string $name) => mb_strtolower($name) === mb_strtolower((string) $match))->values();
    }
}
