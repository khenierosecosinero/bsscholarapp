<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\UserActivity;
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

    public function scholarPageStats(array $programIds, ?User $staff = null): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds, $staff);

        return [
            'total_scholars' => array_sum($statusCounts),
            'active_scholars' => $statusCounts['approved'] ?? 0,
            'pending_scholars' => $statusCounts['pending'] ?? 0,
            'pending_documents' => Document::query()
                ->where('status', 'pending')
                ->whereHas('user', function ($q) use ($programIds, $staff) {
                    $q->where('role', User::ROLE_SCHOLAR)
                        ->whereIn('scholarship_program_id', $programIds ?: [0]);
                    if ($staff?->scholarship_club_id) {
                        $q->where('scholarship_club_id', $staff->scholarship_club_id);
                    }
                })
                ->count(),
        ];
    }

    public function scholarsQuery(array $programIds, ?User $staff = null)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

        if ($staff?->scholarship_club_id) {
            $query->where('scholarship_club_id', $staff->scholarship_club_id);
        }

        return $query;
    }

    public function dashboardStats(array $programIds, ?User $staff = null): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds, $staff);
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

    public function attendanceBreakdown(array $programIds, ?array $filter = null): array
    {
        $base = $this->attendanceQuery($programIds, $filter);

        $approved = (clone $base)->where('status', Attendance::STATUS_APPROVED)->whereNotNull('check_in')->count();
        $pending = (clone $base)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->count();
        $rejected = (clone $base)->where('status', Attendance::STATUS_REJECTED)->count();
        $failedCheckIn = (clone $base)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count();
        $total = max(1, $approved + $pending + $rejected + $failedCheckIn);

        return compact('approved', 'pending', 'rejected', 'failedCheckIn', 'total');
    }

    /**
     * @param  array<int, int>  $programIds
     * @return array{reportFilter: array<string, mixed>, reportYearOptions: array<int, string>, reportSemesterOptions: array<string, string>}
     */
    public function reportContext(Request $request, array $programIds): array
    {
        $yearRaw = $request->query('year', session('staff_report_year'));
        $semesterRaw = $request->query('semester', session('staff_report_semester', AcademicSettingsService::SEMESTER_ALL));
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        $semester = is_string($semesterRaw) ? $semesterRaw : AcademicSettingsService::SEMESTER_ALL;

        $filter = $this->academic->resolveReportFilter($year, $semester);
        session([
            'staff_report_year' => $filter['year_start'],
            'staff_report_semester' => $filter['semester'],
        ]);

        return [
            'reportFilter' => $filter,
            'reportYearOptions' => $this->academic->reportYearOptions($programIds),
            'reportSemesterOptions' => $this->academic->reportSemesterOptions(),
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

    public function serviceHoursReport(array $programIds, ?array $filter = null, ?User $staff = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $required = (float) $this->scholar->periodHourStatsFromRecords([], $filter)['required'];
        $scholars = $this->scholarsQuery($programIds, $staff)->with(['scholarshipProgram', 'scholarshipClub'])->orderBy('full_name')->get();

        $periodQuery = $this->attendanceQuery($programIds, $filter);

        $hourRows = $this->attendanceQuery($programIds, $filter)
            ->whereNotNull('check_in')
            ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
            ->get(['user_id', 'academic_year_start', 'semester', 'status', 'hours_earned'])
            ->groupBy('user_id');

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

        $recent = $this->attendanceQuery($programIds, $filter)
            ->with(['user', 'event'])
            ->whereNotNull('check_in')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'period' => $filter,
            'required' => $required,
            'overview' => $overview,
            'rows' => $rows,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'recent' => $recent,
        ];
    }

    public function attendanceReport(array $programIds, ?array $filter = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $breakdown = $this->attendanceBreakdown($programIds, $filter);
        $checkedIn = $breakdown['approved'] + $breakdown['pending'] + $breakdown['rejected'];
        $records = $this->attendanceQuery($programIds, $filter)
            ->with(['user', 'event'])
            ->latest()
            ->limit(30)
            ->get();

        return [
            'period' => $filter,
            'breakdown' => $breakdown,
            'checked_in' => $checkedIn,
            'rate' => $breakdown['total'] > 0
                ? round(($checkedIn / max(1, $checkedIn + $breakdown['failedCheckIn'])) * 100, 1)
                : 0,
            'records' => $records,
        ];
    }

    public function participationCounts(array $programIds, ?array $filter = null): array
    {
        $registrations = EventRegistration::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));

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
            'participated' => $this->attendanceQuery($programIds, $filter)
                ->where('status', Attendance::STATUS_APPROVED)
                ->whereNotNull('check_in')
                ->count(),
            'pending' => $this->attendanceQuery($programIds, $filter)
                ->where('status', Attendance::STATUS_PENDING)
                ->whereNotNull('check_in')
                ->count(),
        ];
    }

    public function participationReport(array $programIds, ?array $filter = null): array
    {
        $filter ??= $this->defaultReportFilter();
        $counts = $this->participationCounts($programIds, $filter);
        [$start, $end] = $this->academic->eventBoundsForReport($filter);

        $events = Event::query()
            ->with('scholarshipProgram')
            ->withCount([
                'registrations',
                'attendances as checked_in_count' => fn ($q) => $q->whereNotNull('check_in'),
                'attendances as approved_count' => fn ($q) => $q->where('status', Attendance::STATUS_APPROVED),
            ])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->where(function ($query) use ($start, $end, $filter) {
                $query->whereBetween('starts_at', [$start, $end])
                    ->orWhereHas('attendances', fn ($attendance) => $this->academic->scopeAttendancesForReport($attendance, $filter));
            })
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        return array_merge($counts, ['events' => $events, 'period' => $filter]);
    }

    public function completionCounts(array $programIds): array
    {
        $period = $this->academic->current();
        $required = ScholarService::REQUIRED_HOURS;
        $total = $this->scholarsQuery($programIds)->count();

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

    public function completionReport(array $programIds, ?array $filter = null, ?User $staff = null): array
    {
        $report = $this->serviceHoursReport($programIds, $filter, $staff);
        $total = max(1, $report['rows']->count());

        return [
            'period' => $report['period'],
            'required' => $report['required'],
            'completed' => $report['completed'],
            'in_progress' => $report['in_progress'],
            'not_started' => $report['not_started'],
            'completed_pct' => round(($report['completed'] / $total) * 100, 1),
            'rows' => $report['rows'],
        ];
    }

    private function scholarStatusCounts(array $programIds, ?User $staff = null): array
    {
        return $this->scholarsQuery($programIds, $staff)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
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

    private function attendanceQuery(array $programIds, ?array $filter = null)
    {
        $query = Attendance::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));

        if ($filter) {
            $this->academic->scopeAttendancesForReport($query, $filter);
        }

        return $query;
    }
}
