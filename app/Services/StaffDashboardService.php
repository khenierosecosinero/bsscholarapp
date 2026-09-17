<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
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
        $staff->loadMissing('scholarshipProgram');
        $program = $staff->scholarshipProgram;
        $programIds = $this->programIds($staff);

        return [
            'staff' => $staff,
            'program' => $program,
            'programIds' => $programIds,
            'active' => $active,
            'pageTitle' => $title,
            'pageSubtitle' => $subtitle ?: ($program ? 'Managing '.$staff->locationLabel().'.' : 'Manage your assigned scholarship program.'),
            'breadcrumb' => $breadcrumb ?? $title,
            'pendingApprovalsCount' => $this->pendingApprovalsCount($programIds),
        ];
    }

    public function forgetPendingApprovalsCache(array $programIds): void
    {
        Cache::forget('staff.pending_approvals.'.$this->programCacheKey($programIds));
    }

    public function approvalRequestStats(array $programIds): array
    {
        $counts = $this->scholarStatusCounts($programIds);

        return [
            'total' => array_sum($counts),
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
        ];
    }

    public function pendingApprovalsCount(array $programIds): int
    {
        return Cache::remember(
            'staff.pending_approvals.'.$this->programCacheKey($programIds),
            20,
            fn () => $this->scholarsQuery($programIds)->where('status', 'pending')->count()
        );
    }

    public function scholarPageStats(array $programIds): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds);

        return [
            'total_scholars' => array_sum($statusCounts),
            'active_scholars' => $statusCounts['approved'] ?? 0,
            'pending_scholars' => $statusCounts['pending'] ?? 0,
            'pending_documents' => Document::query()
                ->where('status', 'pending')
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                    ->whereIn('scholarship_program_id', $programIds ?: [0]))
                ->count(),
        ];
    }

    public function scholarsQuery(array $programIds)
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);
    }

    public function dashboardStats(array $programIds): array
    {
        $statusCounts = $this->scholarStatusCounts($programIds);
        $totalScholars = array_sum($statusCounts);

        $events = Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

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
        return \App\Models\UserActivity::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->latest()
            ->limit($limit)
            ->with('user')
            ->get();
    }

    public function upcomingEvents(array $programIds, int $limit = 3): Collection
    {
        return Event::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    public function attendanceBreakdown(array $programIds): array
    {
        $base = Attendance::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));

        $approved = (clone $base)->where('status', Attendance::STATUS_APPROVED)->whereNotNull('check_in')->count();
        $pending = (clone $base)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->count();
        $rejected = (clone $base)->where('status', Attendance::STATUS_REJECTED)->count();
        $failedCheckIn = (clone $base)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count();
        $total = max(1, $approved + $pending + $rejected + $failedCheckIn);

        return compact('approved', 'pending', 'rejected', 'failedCheckIn', 'total');
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

    public function serviceHoursReport(array $programIds): array
    {
        $period = $this->academic->current();
        $required = ScholarService::REQUIRED_HOURS;
        $scholars = $this->scholarsQuery($programIds)->with('scholarshipProgram')->orderBy('full_name')->get();

        $periodQuery = $this->attendanceQuery($programIds)
            ->where('academic_year_start', $period->year_start)
            ->where('academic_year_end', $period->year_end)
            ->where('semester', $period->semester);

        $allHourRows = $this->attendanceQuery($programIds)
            ->whereNotNull('check_in')
            ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
            ->get(['user_id', 'academic_year_start', 'semester', 'status', 'hours_earned'])
            ->groupBy('user_id');

        $rows = $scholars->map(function (User $scholar) use ($allHourRows, $period, $required) {
            $stats = $this->scholar->serviceHourStatsFromRecords(
                $allHourRows->get($scholar->id, collect()),
                $period
            );
            $approved = (float) $stats['approved'];
            $pending = (float) $stats['pending'];
            $remaining = (float) $stats['remaining'];
            $status = $approved >= $required
                ? 'Completed'
                : ($approved > 0 || $pending > 0 || ($stats['carried_in'] ?? 0) > 0 ? 'In Progress' : 'Not Started');

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

        $recent = $this->attendanceQuery($programIds)
            ->with(['user', 'event'])
            ->whereNotNull('check_in')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'period' => $period,
            'required' => $required,
            'overview' => $overview,
            'rows' => $rows,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'recent' => $recent,
        ];
    }

    public function attendanceReport(array $programIds): array
    {
        $breakdown = $this->attendanceBreakdown($programIds);
        $checkedIn = $breakdown['approved'] + $breakdown['pending'] + $breakdown['rejected'];
        $records = $this->attendanceQuery($programIds)
            ->with(['user', 'event'])
            ->latest()
            ->limit(30)
            ->get();

        return [
            'breakdown' => $breakdown,
            'checked_in' => $checkedIn,
            'rate' => $breakdown['total'] > 0
                ? round(($checkedIn / max(1, $checkedIn + $breakdown['failedCheckIn'])) * 100, 1)
                : 0,
            'records' => $records,
        ];
    }

    public function participationCounts(array $programIds): array
    {
        $registrations = EventRegistration::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));

        return [
            'registered' => (clone $registrations)->count(),
            'failed' => (clone $registrations)->where('status', EventRegistration::STATUS_FAILED_CHECK_IN)->count(),
            'confirmed' => (clone $registrations)->where('status', EventRegistration::STATUS_CONFIRMED)->count(),
            'participated' => $this->attendanceQuery($programIds)
                ->where('status', Attendance::STATUS_APPROVED)
                ->whereNotNull('check_in')
                ->count(),
            'pending' => $this->attendanceQuery($programIds)
                ->where('status', Attendance::STATUS_PENDING)
                ->whereNotNull('check_in')
                ->count(),
        ];
    }

    public function participationReport(array $programIds): array
    {
        $counts = $this->participationCounts($programIds);

        $events = Event::query()
            ->with('scholarshipProgram')
            ->withCount([
                'registrations',
                'attendances as checked_in_count' => fn ($q) => $q->whereNotNull('check_in'),
                'attendances as approved_count' => fn ($q) => $q->where('status', Attendance::STATUS_APPROVED),
            ])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        return array_merge($counts, compact('events'));
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

    public function completionReport(array $programIds): array
    {
        $report = $this->serviceHoursReport($programIds);
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

    private function scholarStatusCounts(array $programIds): array
    {
        return $this->scholarsQuery($programIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function programCacheKey(array $programIds): string
    {
        $programIds = array_values(array_unique(array_map('intval', $programIds)));
        sort($programIds);

        return md5(implode(',', $programIds));
    }

    private function attendanceQuery(array $programIds)
    {
        return Attendance::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));
    }
}
