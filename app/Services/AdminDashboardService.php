<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(private StaffDashboardService $staff) {}

    /**
     * Resolve location scope from session/request.
     * Returns null for all locations, or array of program IDs for a specific location.
     */
    public function resolveScope(?string $locationKey): ?array
    {
        if ($locationKey === null || $locationKey === '' || $locationKey === 'all') {
            return null;
        }

        $program = ScholarshipProgram::find((int) $locationKey);

        return $program ? $program->coveredLocationIds() : [];
    }

    public function selectedLocation(?string $locationKey): ?ScholarshipProgram
    {
        if ($locationKey === null || $locationKey === '' || $locationKey === 'all') {
            return null;
        }

        return ScholarshipProgram::find((int) $locationKey);
    }

    public function locationOptions(?string $locationKey = null): Collection
    {
        $locations = ScholarshipProgram::active()->get();

        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $selectedId = (int) $locationKey;

            if (! $locations->contains('id', $selectedId)) {
                $selected = ScholarshipProgram::find($selectedId);

                if ($selected) {
                    $locations->push($selected);
                }
            }
        }

        return ScholarshipProgram::sortAlphabetically($locations);
    }

    /**
     * @return array{cities: Collection, provinces: Collection}
     */
    public function locationOptionGroups(?string $locationKey = null): array
    {
        $locations = $this->locationOptions($locationKey);

        return [
            'cities' => ScholarshipProgram::sortAlphabetically(
                $locations->where('location_type', 'city_municipality')
            ),
            'provinces' => ScholarshipProgram::sortAlphabetically(
                $locations->where('location_type', 'province')
            ),
        ];
    }

    public function dashboardStats(?array $programIds): array
    {
        if ($programIds !== null) {
            $stats = $this->staff->dashboardStats($programIds);
            $participation = $this->staff->participationReport($programIds);
            $completion = $this->staff->completionReport($programIds);

            return array_merge($stats, [
                'total_staff' => $this->staffQuery($programIds)->count(),
                'total_attendance' => Attendance::query()
                    ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
                    ->whereNotNull('check_in')
                    ->count(),
                'total_documents' => Document::query()
                    ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
                    ->count(),
                'total_participation' => $participation['participated'] ?? 0,
                'completed_scholars' => $completion['completed'] ?? 0,
                'pending_records' => ($stats['pending_scholars'] ?? 0)
                    + ($stats['pending_attendances'] ?? 0)
                    + ($stats['pending_documents'] ?? 0),
            ]);
        }

        $scholars = User::query()->where('role', User::ROLE_SCHOLAR);
        $staff = User::query()->where('role', User::ROLE_SCHOLAR_STAFF);
        $events = Event::query();
        $attendances = Attendance::query()->whereNotNull('check_in');
        $documents = Document::query();

        $approvedHours = (float) Attendance::query()
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->sum('hours_earned');

        $participated = Attendance::query()
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->count();

        $completion = $this->staff->completionReport(
            ScholarshipProgram::active()->pluck('id')->all()
        );

        return [
            'total_scholars' => (clone $scholars)->count(),
            'active_scholars' => (clone $scholars)->where('status', User::STATUS_APPROVED)->count(),
            'pending_scholars' => (clone $scholars)->where('status', User::STATUS_PENDING)->count(),
            'total_staff' => (clone $staff)->count(),
            'total_events' => (clone $events)->count(),
            'upcoming_events' => (clone $events)->where('starts_at', '>=', now())->count(),
            'total_attendance' => (clone $attendances)->count(),
            'pending_attendances' => Attendance::query()
                ->where('status', Attendance::STATUS_PENDING)
                ->whereNotNull('check_in')
                ->count(),
            'total_service_hours' => number_format($approvedHours, 2),
            'total_documents' => (clone $documents)->count(),
            'pending_documents' => (clone $documents)->where('status', 'pending')->count(),
            'total_participation' => $participated,
            'completed_scholars' => $completion['completed'] ?? 0,
            'pending_records' => (clone $scholars)->where('status', User::STATUS_PENDING)->count()
                + Attendance::query()->where('status', Attendance::STATUS_PENDING)->whereNotNull('check_in')->count()
                + (clone $documents)->where('status', 'pending')->count(),
        ];
    }

    public function locationSummaries(?string $locationKey = null): Collection
    {
        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $program = ScholarshipProgram::find((int) $locationKey);

            return $program
                ? collect([$this->buildLocationSummary($program)])
                : collect();
        }

        return ScholarshipProgram::sortAlphabetically(
            ScholarshipProgram::query()
                ->where('is_active', true)
                ->get()
        )->map(fn (ScholarshipProgram $program) => $this->buildLocationSummary($program));
    }

    private function buildLocationSummary(ScholarshipProgram $program): array
    {
        $ids = $program->coveredLocationIds();
        $stats = $this->staff->dashboardStats($ids);

        return [
            'program' => $program,
            'scholars' => $stats['total_scholars'],
            'staff' => $this->staffQuery($ids)->count(),
            'events' => $stats['total_events'],
            'pending' => ($stats['pending_scholars'] ?? 0) + ($stats['pending_attendances'] ?? 0),
        ];
    }

    public function scholarsQuery(?array $programIds)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->with('scholarshipProgram');

        if ($programIds !== null) {
            $query->whereIn('scholarship_program_id', $programIds ?: [0]);
        }

        return $query;
    }

    public function staffQuery(?array $programIds)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->with('scholarshipProgram');

        if ($programIds !== null) {
            $query->whereIn('scholarship_program_id', $programIds ?: [0]);
        }

        return $query;
    }

    public function eventsQuery(?array $programIds)
    {
        $query = Event::query()->with('scholarshipProgram');

        if ($programIds !== null) {
            $query->whereIn('scholarship_program_id', $programIds ?: [0]);
        }

        return $query;
    }

    public function attendanceQuery(?array $programIds)
    {
        return Attendance::query()
            ->with(['user', 'event'])
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));
    }

    public function documentsQuery(?array $programIds)
    {
        return Document::query()
            ->with(['user', 'documentType'])
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));
    }

    public function documentTypesCount(): int
    {
        return DocumentType::query()->count();
    }

    public function documentOverviewStats(?array $programIds): array
    {
        $query = Document::query()->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));

        $total = (clone $query)->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $pending = (clone $query)->whereIn('status', ['pending', 'submitted'])->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();
        $notSubmitted = (clone $query)->where('status', 'not_submitted')->count();

        return compact('total', 'approved', 'pending', 'rejected', 'notSubmitted');
    }

    private function scopeScholars(Builder $query, ?array $programIds): Builder
    {
        $query->where('role', User::ROLE_SCHOLAR);

        if ($programIds !== null) {
            $query->whereIn('scholarship_program_id', $programIds ?: [0]);
        }

        return $query;
    }
}
