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
            ->where(function ($q) use ($programIds) {
                $q->whereNull('scholarship_program_id');
                if ($programIds) {
                    $q->orWhereIn('scholarship_program_id', $programIds);
                }
            });

        $pendingAttendances = Attendance::query()
            ->where('status', 'pending')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->count();

        $pendingDocuments = Document::query()
            ->where('status', 'pending')
            ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds ?: [0]))
            ->count();

        $totalServiceHours = Attendance::query()
            ->where('status', 'approved')
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

        $approved = (clone $base)->where('status', 'approved')->count();
        $pending = (clone $base)->where('status', 'pending')->count();
        $rejected = (clone $base)->where('status', 'rejected')->count();
        $total = max(1, $approved + $pending + $rejected);

        return compact('approved', 'pending', 'rejected', 'total');
    }
}
