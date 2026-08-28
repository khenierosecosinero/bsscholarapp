<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

class StaffDashboardService
{
    public function __construct(private AcademicSettingsService $academic) {}

    public function programIds(User $staff): array
    {
        return $staff->managedLocationIds();
    }

    public function scholarsQuery(array $programIds)
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);
    }

    public function dashboardStats(array $programIds): array
    {
        $scholars = $this->scholarsQuery($programIds);
        $totalScholars = (clone $scholars)->count();
        $activeScholars = (clone $scholars)->where('status', 'approved')->count();
        $pendingScholars = (clone $scholars)->where('status', 'pending')->count();

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
            'active_scholars' => $activeScholars,
            'pending_scholars' => $pendingScholars,
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
            ->where(function ($q) use ($programIds) {
                $q->whereNull('scholarship_program_id');
                if ($programIds) {
                    $q->orWhereIn('scholarship_program_id', $programIds);
                }
            })
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

        $overview = [
            'approved_hours' => (float) (clone $periodQuery)->where('status', Attendance::STATUS_APPROVED)->whereNotNull('check_in')->sum('hours_earned'),
            'pending_hours' => (float) (clone $periodQuery)->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->sum('hours_earned'),
            'rejected_count' => (clone $periodQuery)->where('status', Attendance::STATUS_REJECTED)->count(),
            'failed_count' => (clone $periodQuery)->where('status', Attendance::STATUS_FAILED_CHECK_IN)->count(),
        ];

        $hourRows = (clone $periodQuery)
            ->get(['user_id', 'status', 'hours_earned'])
            ->groupBy('user_id');

        $rows = $scholars->map(function (User $scholar) use ($hourRows, $required) {
            $byStatus = $hourRows->get($scholar->id, collect());
            $approved = (float) $byStatus->where('status', Attendance::STATUS_APPROVED)->sum('hours_earned');
            $pending = (float) $byStatus->where('status', Attendance::STATUS_PENDING)->sum('hours_earned');
            $remaining = max(0, $required - $approved);
            $status = $approved >= $required ? 'Completed' : ($approved > 0 || $pending > 0 ? 'In Progress' : 'Not Started');

            return [
                'scholar' => $scholar,
                'approved' => $approved,
                'pending' => $pending,
                'remaining' => $remaining,
                'status' => $status,
            ];
        });

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

    public function participationReport(array $programIds): array
    {
        $registrations = EventRegistration::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));

        $registered = (clone $registrations)->count();
        $failed = (clone $registrations)->where('status', EventRegistration::STATUS_FAILED_CHECK_IN)->count();
        $confirmed = (clone $registrations)->where('status', EventRegistration::STATUS_CONFIRMED)->count();
        $participated = $this->attendanceQuery($programIds)
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->count();
        $pending = $this->attendanceQuery($programIds)
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->count();

        $events = Event::query()
            ->withCount([
                'registrations',
                'attendances as checked_in_count' => fn ($q) => $q->whereNotNull('check_in'),
                'attendances as approved_count' => fn ($q) => $q->where('status', Attendance::STATUS_APPROVED),
            ])
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        return compact('registered', 'failed', 'confirmed', 'participated', 'pending', 'events');
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

    private function attendanceQuery(array $programIds)
    {
        return Attendance::query()
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]));
    }
}
