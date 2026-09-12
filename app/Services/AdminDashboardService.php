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
    public function __construct(
        private StaffDashboardService $staff,
        private ProgramScopeService $programScope,
    ) {}

    public function syncProgramType(?string $programType): string
    {
        $programType = $programType ?: 'all';

        return in_array($programType, ['all', 'city_municipality', 'province'], true)
            ? $programType
            : 'all';
    }

    private function filterProgramsByType(Collection $programs, string $programType): Collection
    {
        if ($programType === 'city_municipality') {
            return $programs->where('location_type', 'city_municipality');
        }

        if ($programType === 'province') {
            return $programs->where('location_type', 'province');
        }

        return $programs;
    }

    /**
     * Resolve program IDs for admin queries.
     * Always returns an explicit list so city and province records never mix unintentionally.
     */
    public function resolveAdminProgramIds(?string $locationKey, string $programType = 'all'): array
    {
        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $program = ScholarshipProgram::find((int) $locationKey);

            return $program ? $program->coveredLocationIds() : [0];
        }

        $query = ScholarshipProgram::active();

        if ($programType === 'city_municipality') {
            return $query->cities()->pluck('id')->all() ?: [0];
        }

        if ($programType === 'province') {
            return $query->provinces()->pluck('id')->all() ?: [0];
        }

        return $query->pluck('id')->all() ?: [0];
    }

    /**
     * @deprecated Use resolveAdminProgramIds() for admin pages.
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
    public function locationOptionGroups(?string $locationKey = null, string $programType = 'all'): array
    {
        $locations = $this->filterProgramsByType($this->locationOptions($locationKey), $programType);

        return [
            'cities' => ScholarshipProgram::sortAlphabetically(
                $locations->where('location_type', 'city_municipality')
            ),
            'provinces' => ScholarshipProgram::sortAlphabetically(
                $locations->where('location_type', 'province')
            ),
        ];
    }

    public function dashboardStats(array $programIds): array
    {
        $stats = $this->staff->dashboardStats($programIds);
        $participation = $this->staff->participationReport($programIds);
        $completion = $this->staff->completionReport($programIds);

        $cityIds = array_values(array_intersect(
            $programIds,
            ScholarshipProgram::active()->cities()->pluck('id')->all()
        ));
        $provinceIds = array_values(array_intersect(
            $programIds,
            ScholarshipProgram::active()->provinces()->pluck('id')->all()
        ));

        return array_merge($stats, [
            'total_staff' => $this->approvedStaffQuery($programIds)->count(),
            'pending_staff' => $this->pendingStaffQuery($programIds)->count(),
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
            'program_type_totals' => $this->programScope->programTypeTotals(),
            'city_program_stats' => $cityIds ? $this->staff->dashboardStats($cityIds) : null,
            'province_program_stats' => $provinceIds ? $this->staff->dashboardStats($provinceIds) : null,
        ]);
    }

    public function locationSummaries(?string $locationKey = null, string $programType = 'all'): Collection
    {
        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $program = ScholarshipProgram::find((int) $locationKey);

            return $program
                ? collect([$this->buildLocationSummary($program)])
                : collect();
        }

        $programs = ScholarshipProgram::sortAlphabetically(
            $this->filterProgramsByType(
                ScholarshipProgram::query()->where('is_active', true)->get(),
                $programType
            )
        );

        return $programs->map(fn (ScholarshipProgram $program) => $this->buildLocationSummary($program));
    }

    private function buildLocationSummary(ScholarshipProgram $program): array
    {
        $ids = $program->coveredLocationIds();
        $stats = $this->staff->dashboardStats($ids);

        return [
            'program' => $program,
            'scholars' => $stats['total_scholars'],
            'staff' => $this->approvedStaffQuery($ids)->count(),
            'events' => $stats['total_events'],
            'pending' => ($stats['pending_scholars'] ?? 0) + ($stats['pending_attendances'] ?? 0),
        ];
    }

    public function scholarsQuery(array $programIds)
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->with('scholarshipProgram');
    }

    public function staffQuery(array $programIds)
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->with('scholarshipProgram');
    }

    public function approvedStaffQuery(array $programIds)
    {
        return $this->staffQuery($programIds)->where('status', User::STATUS_APPROVED);
    }

    public function visibleStaffQuery(array $programIds)
    {
        return $this->staffQuery($programIds)->where('status', '!=', User::STATUS_PENDING);
    }

    public function pendingStaffQuery(array $programIds)
    {
        return $this->staffQuery($programIds)->where('status', User::STATUS_PENDING);
    }

    public function eventsQuery(array $programIds)
    {
        return Event::query()
            ->with('scholarshipProgram')
            ->whereIn('scholarship_program_id', $programIds ?: [0]);
    }

    public function attendanceQuery(array $programIds)
    {
        return Attendance::query()
            ->with(['user.scholarshipProgram', 'event'])
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));
    }

    public function documentsQuery(array $programIds)
    {
        return Document::query()
            ->with(['user', 'documentType'])
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));
    }

    public function documentTypesCount(array $programIds): int
    {
        return DocumentType::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->count();
    }

    public function documentOverviewStats(array $programIds): array
    {
        $query = Document::query()->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds));

        $total = (clone $query)->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $pending = (clone $query)->whereIn('status', ['pending', 'submitted'])->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();
        $notSubmitted = (clone $query)->where('status', 'not_submitted')->count();

        return compact('total', 'approved', 'pending', 'rejected', 'notSubmitted');
    }

    private function scopeScholars(Builder $query, array $programIds): Builder
    {
        return $query
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);
    }

    /**
     * Pending/new item counts for each admin sidebar section (scoped to program IDs).
     *
     * @return array<string, int>
     */
    public function adminSidebarBadges(array $programIds): array
    {
        $programIds = $programIds ?: [0];

        $pendingScholars = $this->scholarsQuery($programIds)
            ->where('status', User::STATUS_PENDING)
            ->count();

        $pendingStaff = $this->pendingStaffQuery($programIds)->count();

        $pendingEvents = $this->eventsQuery($programIds)
            ->where('status', 'pending')
            ->count();

        $pendingAttendances = Attendance::query()
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
            ->count();

        $pendingServiceHours = Attendance::query()
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->where('hours_earned', '>', 0)
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
            ->distinct('user_id')
            ->count('user_id');

        $pendingDocuments = Document::query()
            ->whereIn('status', ['pending', 'submitted'])
            ->whereNotNull('file_path')
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
            ->count();

        $participationReport = $this->staff->participationReport($programIds);
        $pendingParticipation = ($participationReport['pending'] ?? 0) + ($participationReport['failed'] ?? 0);

        $pendingStaffProgramIds = $this->pendingStaffQuery($programIds)
            ->pluck('scholarship_program_id');

        $inactiveProgramIds = ScholarshipProgram::query()
            ->whereIn('id', $programIds)
            ->where('is_active', false)
            ->pluck('id');

        $locationsAttention = $pendingStaffProgramIds
            ->merge($inactiveProgramIds)
            ->unique()
            ->count();

        $completionReport = $this->staff->completionReport($programIds);
        $reportsAttention = ($completionReport['not_started'] ?? 0) + ($completionReport['in_progress'] ?? 0);

        $badges = [
            'scholars' => $pendingScholars,
            'staff' => $pendingStaff,
            'events' => $pendingEvents,
            'attendance' => $pendingAttendances,
            'service-hours' => $pendingServiceHours,
            'documents' => $pendingDocuments,
            'participation' => $pendingParticipation,
            'locations' => $locationsAttention,
            'reports' => $reportsAttention,
            'settings' => 0,
        ];

        $badges['dashboard'] = $pendingScholars
            + $pendingStaff
            + $pendingEvents
            + $pendingAttendances
            + $pendingDocuments
            + $locationsAttention;

        return $badges;
    }
}
