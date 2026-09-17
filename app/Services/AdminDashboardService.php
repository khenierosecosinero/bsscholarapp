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
use Illuminate\Support\Facades\Cache;

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
        $locations = Cache::remember('admin.active_programs', 60, function () {
            return ScholarshipProgram::active()->get();
        });

        $locations = $locations->values();

        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $selectedId = (int) $locationKey;

            if (! $locations->contains('id', $selectedId)) {
                $selected = ScholarshipProgram::find($selectedId);

                if ($selected) {
                    $locations = $locations->push($selected)->values();
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
        $participation = $this->staff->participationCounts($programIds);
        $completion = $this->staff->completionCounts($programIds);

        return array_merge($stats, [
            'total_staff' => $this->approvedStaffQuery($programIds)->count(),
            'pending_staff' => $this->pendingStaffQuery($programIds)->count(),
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

    /**
     * Pie-chart series for a single Admin location, built from reports already loaded
     * for that program scope so the page does not repeat expensive aggregations.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $completionReport
     * @param  array<string, mixed>  $participationReport
     * @return array<string, array{title: string, total: int|float, slices: array<int, array<string, mixed>>}>
     */
    public function locationReportCharts(array $programIds, array $stats, array $completionReport, array $participationReport): array
    {
        $staffStatus = User::query()
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $participationSlices = [
            ['label' => 'Participated', 'value' => (int) ($participationReport['participated'] ?? 0), 'color' => '#16a34a'],
            ['label' => 'Pending', 'value' => (int) ($participationReport['pending'] ?? 0), 'color' => '#f59e0b'],
            ['label' => 'Failed check-in', 'value' => (int) ($participationReport['failed'] ?? 0), 'color' => '#ef4444'],
        ];

        if (array_sum(array_column($participationSlices, 'value')) === 0 && (int) ($participationReport['registered'] ?? 0) > 0) {
            $participationSlices = [
                ['label' => 'Registered', 'value' => (int) $participationReport['registered'], 'color' => '#2563eb'],
            ];
        }

        return [
            'scholars' => $this->buildPieChart('Scholars', [
                ['label' => 'Approved', 'value' => (int) ($stats['active_scholars'] ?? 0), 'color' => '#16a34a'],
                ['label' => 'Pending', 'value' => (int) ($stats['pending_scholars'] ?? 0), 'color' => '#f59e0b'],
                ['label' => 'Rejected', 'value' => (int) ($stats['rejected_scholars'] ?? 0), 'color' => '#ef4444'],
            ]),
            'staff' => $this->buildPieChart('Staff', [
                ['label' => 'Approved', 'value' => (int) ($staffStatus[User::STATUS_APPROVED] ?? $stats['total_staff'] ?? 0), 'color' => '#16a34a'],
                ['label' => 'Pending', 'value' => (int) ($staffStatus[User::STATUS_PENDING] ?? $stats['pending_staff'] ?? 0), 'color' => '#f59e0b'],
                ['label' => 'Rejected', 'value' => (int) ($staffStatus[User::STATUS_REJECTED] ?? 0), 'color' => '#ef4444'],
            ]),
            'completed' => $this->buildPieChart('Completed', [
                ['label' => 'Completed', 'value' => (int) ($completionReport['completed'] ?? 0), 'color' => '#16a34a'],
                ['label' => 'In progress', 'value' => (int) ($completionReport['in_progress'] ?? 0), 'color' => '#f59e0b'],
                ['label' => 'Not started', 'value' => (int) ($completionReport['not_started'] ?? 0), 'color' => '#94a3b8'],
            ]),
            'participation' => $this->buildPieChart('Participation', $participationSlices),
        ];
    }

    /**
     * @param  array<int, array{label: string, value: int|float, color: string}>  $slices
     * @return array{title: string, total: int|float, gradient: string, slices: array<int, array<string, mixed>>}
     */
    private function buildPieChart(string $title, array $slices): array
    {
        $total = array_sum(array_map(fn (array $slice) => (float) $slice['value'], $slices));
        $remaining = 100.0;
        $lastPositive = null;

        foreach ($slices as $index => $slice) {
            if ((float) $slice['value'] > 0) {
                $lastPositive = $index;
            }
        }

        $formatted = [];
        $stops = [];

        foreach ($slices as $index => $slice) {
            $value = (float) $slice['value'];
            $pct = $total > 0 ? round(($value / $total) * 100, 1) : 0.0;

            if ($total > 0 && $index === $lastPositive) {
                $pct = round($remaining, 1);
            } else {
                $remaining -= $pct;
            }

            $row = [
                'label' => $slice['label'],
                'value' => $value,
                'color' => $slice['color'],
                'pct' => $pct,
            ];
            $formatted[] = $row;

            if ($value > 0 && $total > 0) {
                $stops[] = $row;
            }
        }

        $gradient = '';
        if ($stops) {
            $cursor = 0.0;
            $parts = [];
            foreach ($stops as $stop) {
                $end = $cursor + (float) $stop['pct'];
                $parts[] = $stop['color'].' '.$cursor.'% '.$end.'%';
                $cursor = $end;
            }
            $gradient = 'conic-gradient('.implode(', ', $parts).')';
        }

        return [
            'title' => $title,
            'total' => $total,
            'gradient' => $gradient,
            'slices' => $formatted,
        ];
    }

    public function locationBoard(?string $locationKey = null, string $programType = 'all'): array
    {
        $all = $this->locationSummaries('all', 'all');
        $citySummaries = $all->filter(
            fn (array $summary) => ($summary['program']->location_type ?? null) === 'city_municipality'
        )->values();
        $provinceSummaries = $all->filter(
            fn (array $summary) => ($summary['program']->location_type ?? null) === 'province'
        )->values();

        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $summaries = $all->filter(
                fn (array $summary) => (int) $summary['program']->id === (int) $locationKey
            )->values();

            if ($summaries->isEmpty()) {
                $summaries = $this->locationSummaries($locationKey, $programType);
            }
        } else {
            $summaries = match ($programType) {
                'city_municipality' => $citySummaries,
                'province' => $provinceSummaries,
                default => $all,
            };
        }

        return compact('summaries', 'citySummaries', 'provinceSummaries');
    }

    public function locationSummaries(?string $locationKey = null, string $programType = 'all'): Collection
    {
        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $program = ScholarshipProgram::find((int) $locationKey);

            if (! $program) {
                return collect();
            }

            $maps = $this->locationMetricMaps([$program->id]);

            return collect([$this->buildLocationSummaryFromMaps($program, $maps)]);
        }

        $programs = ScholarshipProgram::sortAlphabetically(
            $this->filterProgramsByType(
                ScholarshipProgram::query()->where('is_active', true)->get(),
                $programType
            )
        );

        $maps = $this->locationMetricMaps($programs->pluck('id')->all());

        return $programs->map(fn (ScholarshipProgram $program) => $this->buildLocationSummaryFromMaps($program, $maps));
    }

    private function buildLocationSummary(ScholarshipProgram $program): array
    {
        return $this->buildLocationSummaryFromMaps($program, $this->locationMetricMaps($program->coveredLocationIds()));
    }

    private function buildLocationSummaryFromMaps(ScholarshipProgram $program, array $maps): array
    {
        $id = (int) $program->id;

        return [
            'program' => $program,
            'scholars' => (int) ($maps['scholars'][$id] ?? 0),
            'staff' => (int) ($maps['staff'][$id] ?? 0),
            'events' => (int) ($maps['events'][$id] ?? 0),
            'pending' => (int) ($maps['pending_scholars'][$id] ?? 0) + (int) ($maps['pending_attendances'][$id] ?? 0),
        ];
    }

    /**
     * @return array{scholars: Collection, staff: Collection, events: Collection, pending_scholars: Collection, pending_attendances: Collection}
     */
    private function locationMetricMaps(array $programIds): array
    {
        $programIds = $programIds ?: [0];

        return [
            'scholars' => User::query()
                ->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $programIds)
                ->selectRaw('scholarship_program_id, count(*) as aggregate')
                ->groupBy('scholarship_program_id')
                ->pluck('aggregate', 'scholarship_program_id'),
            'pending_scholars' => User::query()
                ->where('role', User::ROLE_SCHOLAR)
                ->where('status', User::STATUS_PENDING)
                ->whereIn('scholarship_program_id', $programIds)
                ->selectRaw('scholarship_program_id, count(*) as aggregate')
                ->groupBy('scholarship_program_id')
                ->pluck('aggregate', 'scholarship_program_id'),
            'staff' => User::query()
                ->where('role', User::ROLE_SCHOLAR_STAFF)
                ->where('status', User::STATUS_APPROVED)
                ->whereIn('scholarship_program_id', $programIds)
                ->selectRaw('scholarship_program_id, count(*) as aggregate')
                ->groupBy('scholarship_program_id')
                ->pluck('aggregate', 'scholarship_program_id'),
            'events' => Event::query()
                ->whereIn('scholarship_program_id', $programIds)
                ->selectRaw('scholarship_program_id, count(*) as aggregate')
                ->groupBy('scholarship_program_id')
                ->pluck('aggregate', 'scholarship_program_id'),
            'pending_attendances' => Attendance::query()
                ->join('users', 'attendances.user_id', '=', 'users.id')
                ->where('attendances.status', Attendance::STATUS_PENDING)
                ->whereNotNull('attendances.check_in')
                ->where('users.role', User::ROLE_SCHOLAR)
                ->whereIn('users.scholarship_program_id', $programIds)
                ->selectRaw('users.scholarship_program_id, count(*) as aggregate')
                ->groupBy('users.scholarship_program_id')
                ->pluck('aggregate', 'scholarship_program_id'),
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
        $payload = $this->sidebarBadgePayload($programIds);
        $badges = $payload['badges'];
        $badges['reports'] = $this->unreadReportsCount($programIds, $payload);

        return $badges;
    }

    public function markReportsViewed(array $programIds): void
    {
        $payload = $this->sidebarBadgePayload($programIds);
        session([$this->reportsSeenSessionKey($programIds) => $payload['reports_signature']]);
    }

    /**
     * @return array{badges: array<string, int>, reports_attention: int, reports_signature: string}
     */
    private function sidebarBadgePayload(array $programIds): array
    {
        $programIds = $programIds ?: [0];
        $cacheKey = 'admin.sidebar_badges.v2.'.md5(implode(',', array_map('intval', $programIds)));

        return Cache::remember($cacheKey, 20, function () use ($programIds) {
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

            $failedParticipation = EventRegistration::query()
                ->where('status', EventRegistration::STATUS_FAILED_CHECK_IN)
                ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds))
                ->count();

            $completion = $this->staff->completionCounts($programIds);

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

            $reportsAttention = (int) ($completion['in_progress'] ?? 0)
                + (int) ($completion['not_started'] ?? 0);

            $badges = [
                'scholars' => $pendingScholars,
                'staff' => $pendingStaff,
                'events' => $pendingEvents,
                'service-hours' => $pendingServiceHours,
                'documents' => $pendingDocuments,
                'participation' => $pendingAttendances + $failedParticipation,
                'locations' => $locationsAttention,
                'reports' => 0,
                'settings' => 0,
            ];

            $badges['dashboard'] = $pendingScholars
                + $pendingStaff
                + $pendingEvents
                + $pendingAttendances
                + $pendingDocuments
                + $locationsAttention;

            return [
                'badges' => $badges,
                'reports_attention' => $reportsAttention,
                'reports_signature' => md5(json_encode([
                    'completed' => (int) ($completion['completed'] ?? 0),
                    'in_progress' => (int) ($completion['in_progress'] ?? 0),
                    'not_started' => (int) ($completion['not_started'] ?? 0),
                    'approved_hours' => (float) ($completion['approved_hours'] ?? 0),
                    'pending_attendances' => $pendingAttendances,
                    'failed_participation' => $failedParticipation,
                ])),
            ];
        });
    }

    /**
     * @param  array{badges: array<string, int>, reports_attention: int, reports_signature: string}  $payload
     */
    private function unreadReportsCount(array $programIds, array $payload): int
    {
        $attention = (int) ($payload['reports_attention'] ?? 0);
        if ($attention < 1) {
            return 0;
        }

        $seen = session($this->reportsSeenSessionKey($programIds));
        if (is_string($seen) && $seen === ($payload['reports_signature'] ?? '')) {
            return 0;
        }

        return $attention;
    }

    private function reportsSeenSessionKey(array $programIds): string
    {
        $programIds = array_values(array_unique(array_map('intval', $programIds ?: [0])));
        sort($programIds);

        return 'admin_reports_seen.'.md5(implode(',', $programIds));
    }

    public function forgetLocationCaches(): void
    {
        Cache::forget('admin.active_programs');
    }
}
