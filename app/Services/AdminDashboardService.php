<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Support\PhilippineIslandGroup;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AdminDashboardService
{
    public function __construct(
        private StaffDashboardService $staff,
        private ProgramScopeService $programScope,
        private AcademicSettingsService $academic,
        private ScholarService $scholar,
    ) {}

    public function syncProgramType(?string $programType): string
    {
        $programType = $programType ?: 'city_municipality';

        if ($programType === 'all') {
            return 'city_municipality';
        }

        return in_array($programType, ['city_municipality', 'province'], true)
            ? $programType
            : 'city_municipality';
    }

    /**
     * Resolve program IDs for admin queries.
     * City Scholar and Province Scholar stay separate. A selected province
     * under City Scholar includes that province's city programs only.
     */
    public function resolveAdminProgramIds(?string $locationKey, string $programType = 'city_municipality'): array
    {
        $programType = $this->syncProgramType($programType);

        if ($locationKey !== null && $locationKey !== '' && $locationKey !== 'all') {
            $program = ScholarshipProgram::find((int) $locationKey);

            if (! $program) {
                return [0];
            }

            if ($programType === 'city_municipality') {
                if ($program->isCityOrMunicipality()) {
                    return [$program->id];
                }

                return ScholarshipProgram::active()
                    ->cities()
                    ->where('province_name', $program->location_name)
                    ->pluck('id')
                    ->all() ?: [0];
            }

            if ($program->isProvince()) {
                return [$program->id];
            }

            $province = ScholarshipProgram::active()
                ->provinces()
                ->where('location_name', $program->province_name)
                ->first();

            return $province ? [$province->id] : [0];
        }

        if ($programType === 'city_municipality') {
            return ScholarshipProgram::active()->cities()->pluck('id')->all() ?: [0];
        }

        return ScholarshipProgram::active()->provinces()->pluck('id')->all() ?: [0];
    }

    /**
     * Program IDs for Admin Dashboard cards: Province and Municipality/City
     * only. City Scholar / Province Scholar category is not used here.
     *
     * @return list<int>
     */
    public function resolveGeographicProgramIds(?string $locationKey): array
    {
        if ($locationKey === null || $locationKey === '' || $locationKey === 'all') {
            return ScholarshipProgram::active()->pluck('id')->all() ?: [0];
        }

        $program = ScholarshipProgram::find((int) $locationKey);

        if (! $program) {
            return [0];
        }

        if ($program->isCityOrMunicipality()) {
            return [$program->id];
        }

        $ids = ScholarshipProgram::active()
            ->cities()
            ->where('province_name', $program->location_name)
            ->pluck('id')
            ->all();

        $ids[] = $program->id;

        return array_values(array_unique(array_map('intval', $ids))) ?: [0];
    }

    /**
     * Scholarship Clubs registered in the selected Province / Municipality/City.
     */
    public function geographicClubs(?string $locationKey)
    {
        $address = $this->selectedAddress($locationKey);

        return ScholarshipClub::query()
            ->active()
            ->forLocation($address['province'], $address['city'])
            ->orderBy('name')
            ->orderBy('city')
            ->get();
    }

    /**
     * @return array{province: ?string, city: ?string}
     */
    public function selectedAddress(?string $locationKey): array
    {
        $program = $this->selectedLocation($locationKey);

        if (! $program) {
            return ['province' => null, 'city' => null];
        }

        $location = $program->registrationLocation();

        return [
            'province' => $location['province'] ?: null,
            'city' => $location['city'] ?: null,
        ];
    }

    /**
     * Scholarship Clubs registered under the admin's selected location.
     */
    public function clubsForAdminLocation(?string $locationKey, string $programType = 'city_municipality')
    {
        $programType = $this->syncProgramType($programType);
        $address = $this->selectedAddress($locationKey);

        $query = ScholarshipClub::query()
            ->active()
            ->forLocation($address['province'], $programType === 'city_municipality' ? $address['city'] : null)
            ->orderBy('name')
            ->orderBy('city');

        if ($programType === 'city_municipality') {
            $query->whereHas('program', fn ($q) => $q->where('location_type', 'city_municipality'));
        } elseif (! filled($address['province'])) {
            $query->whereHas('program', fn ($q) => $q->where('location_type', 'province'));
        }

        return $query->get();
    }

    /**
     * Club IDs used to filter City Scholar lists when a location is selected.
     * Null means do not extra-filter (category overview).
     *
     * @return list<int>|null
     */
    public function clubIdsForAdminQueries(?string $locationKey, string $programType = 'city_municipality'): ?array
    {
        $programType = $this->syncProgramType($programType);

        if ($locationKey === null || $locationKey === '' || $locationKey === 'all') {
            return null;
        }

        if ($programType === 'province') {
            return null;
        }

        return $this->clubsForAdminLocation($locationKey, $programType)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    private function filterProgramsByType(Collection $programs, string $programType): Collection
    {
        $programType = $this->syncProgramType($programType);

        if ($programType === 'city_municipality') {
            return $programs->where('location_type', 'city_municipality');
        }

        return $programs->where('location_type', 'province');
    }

    /**
     * @return array{cities: Collection, provinces: Collection}
     */
    public function locationOptionGroups(?string $locationKey = null, string $programType = 'city_municipality'): array
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

    public function dashboardStats(array $programIds, ?array $clubIds = null, ?int $totalClubs = null): array
    {
        $stats = $this->staff->dashboardStats($programIds, null, $clubIds);
        $participation = $this->staff->participationCounts($programIds);
        $completion = $this->staff->completionCounts($programIds, $clubIds);

        return array_merge($stats, [
            'total_staff' => $this->approvedStaffQuery($programIds, $clubIds)->count(),
            'pending_staff' => $this->pendingStaffQuery($programIds, $clubIds)->count(),
            'total_documents' => Document::query()
                ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds, $clubIds))
                ->count(),
            'total_clubs' => $totalClubs ?? (
                $clubIds !== null
                    ? count($clubIds)
                    : ScholarshipClub::query()->active()->count()
            ),
            'total_participation' => $participation['participated'] ?? 0,
            'completed_scholars' => $completion['completed'] ?? 0,
            'pending_records' => ($stats['pending_scholars'] ?? 0)
                + ($stats['pending_attendances'] ?? 0)
                + ($stats['pending_documents'] ?? 0),
        ]);
    }

    /**
     * Academic Year selector for Admin Reports (no semester; all three regions share this year).
     *
     * @return array{reportFilter: array<string, mixed>, reportYearOptions: array<int, string>}
     */
    public function reportContext(Request $request): array
    {
        $yearRaw = $request->query('year', session('admin_report_year'));
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        $filter = $this->academic->resolveReportFilter($year, AcademicSettingsService::SEMESTER_ALL);
        session(['admin_report_year' => $filter['year_start']]);

        return [
            'reportFilter' => $filter,
            'reportYearOptions' => $this->academic->reportYearOptions(),
        ];
    }

    /**
     * Luzon, Visayas, and Mindanao reports for one Academic Year.
     *
     * @param  array{year_start: int, year_end: int, semester: string, academic_year: string}  $filter
     * @return array<string, array<string, mixed>>
     */
    public function regionalReports(array $filter): array
    {
        $programIdsByIsland = $this->programIdsByIsland();
        [, $ayEnd] = $this->academic->eventBoundsForReport($filter);

        $regions = [];
        foreach (PhilippineIslandGroup::LABELS as $key => $label) {
            $regions[$key] = $this->regionReport(
                $key,
                $label,
                $programIdsByIsland[$key] ?? [],
                $filter,
                $ayEnd
            );
        }

        return $regions;
    }

    /**
     * @return array{luzon: array<int, int>, visayas: array<int, int>, mindanao: array<int, int>}
     */
    public function programIdsByIsland(): array
    {
        $programs = ScholarshipProgram::query()
            ->get(['id', 'region_name', 'psgc_code', 'province_name', 'location_name', 'location_type']);

        $classified = [
            PhilippineIslandGroup::LUZON => [],
            PhilippineIslandGroup::VISAYAS => [],
            PhilippineIslandGroup::MINDANAO => [],
        ];
        $assigned = [];
        $provinceMap = [];

        foreach ($programs as $program) {
            $island = PhilippineIslandGroup::fromProgram($program);
            if (! $island) {
                continue;
            }

            $classified[$island][] = (int) $program->id;
            $assigned[(int) $program->id] = true;

            if ($program->isProvince() && filled($program->location_name)) {
                $provinceMap[mb_strtolower((string) $program->location_name)] = $island;
            }
        }

        foreach ($programs as $program) {
            if (isset($assigned[(int) $program->id])) {
                continue;
            }

            $province = $program->isProvince()
                ? $program->location_name
                : $program->province_name;
            $island = $provinceMap[mb_strtolower(trim((string) $province))] ?? null;
            if (! $island) {
                continue;
            }

            $classified[$island][] = (int) $program->id;
        }

        return $classified;
    }

    /**
     * @param  array<int, int>  $programIds
     * @param  array{year_start: int, semester: string, academic_year: string}  $filter
     * @return array<string, mixed>
     */
    private function regionReport(
        string $key,
        string $label,
        array $programIds,
        array $filter,
        CarbonInterface $ayEnd,
    ): array {
        $ids = $programIds ?: [0];
        $year = (int) $filter['year_start'];
        $hourFilter = [
            'year_start' => $year,
            'semester' => AcademicSettingsService::SEMESTER_ALL,
        ];
        $required = (float) ScholarService::REQUIRED_HOURS;

        $scholarCounts = $this->regionScholarsQuery($ids, $year, $ayEnd)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $approvedScholars = (int) ($scholarCounts[User::STATUS_APPROVED] ?? 0);
        $pendingScholars = (int) ($scholarCounts[User::STATUS_PENDING] ?? 0);
        $rejectedScholars = (int) ($scholarCounts[User::STATUS_REJECTED] ?? 0);
        $totalScholars = $approvedScholars + $pendingScholars + $rejectedScholars;

        $totalClubs = ScholarshipClub::query()
            ->active()
            ->whereIn('scholarship_program_id', $ids)
            ->where('created_at', '<=', $ayEnd)
            ->count();

        $hourRows = Attendance::query()
            ->where('academic_year_start', $year)
            ->whereNotNull('check_in')
            ->whereIn('status', [Attendance::STATUS_APPROVED, Attendance::STATUS_PENDING])
            ->whereHas('user', fn ($query) => $query
                ->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $ids))
            ->get(['user_id', 'academic_year_start', 'semester', 'status', 'hours_earned'])
            ->groupBy('user_id');

        $completed = 0;
        $inProgress = 0;

        foreach ($hourRows as $records) {
            $stats = $this->scholar->periodHourStatsFromRecords($records, $hourFilter);
            $approvedHours = (float) $stats['approved'];
            $pendingHours = (float) $stats['pending'];

            if ($approvedHours >= $required) {
                $completed++;
            } elseif ($approvedHours > 0 || $pendingHours > 0) {
                $inProgress++;
            }
        }

        $notStarted = max(0, $totalScholars - $completed - $inProgress);
        $completedPct = $totalScholars > 0 ? round(($completed / $totalScholars) * 100, 1) : 0.0;

        $participationBase = Attendance::query()
            ->where('academic_year_start', $year)
            ->whereHas('user', fn ($query) => $query
                ->where('role', User::ROLE_SCHOLAR)
                ->whereIn('scholarship_program_id', $ids));

        $approvedParticipation = (clone $participationBase)
            ->where('status', Attendance::STATUS_APPROVED)
            ->whereNotNull('check_in')
            ->count();
        $pendingParticipation = (clone $participationBase)
            ->where('status', Attendance::STATUS_PENDING)
            ->whereNotNull('check_in')
            ->count();
        $failedParticipation = (clone $participationBase)
            ->where('status', Attendance::STATUS_FAILED_CHECK_IN)
            ->count();
        $rejectedParticipation = (clone $participationBase)
            ->where('status', Attendance::STATUS_REJECTED)
            ->count();
        $totalParticipation = (clone $participationBase)->count();

        return [
            'key' => $key,
            'label' => $label,
            'academic_year' => $filter['academic_year'],
            'scholars' => [
                'total' => $totalScholars,
                'approved' => $approvedScholars,
                'pending' => $pendingScholars,
                'rejected' => $rejectedScholars,
                'chart' => $this->buildPieChart('Scholars', [
                    ['label' => 'Approved', 'value' => $approvedScholars, 'color' => '#16a34a'],
                    ['label' => 'Pending', 'value' => $pendingScholars, 'color' => '#f59e0b'],
                    ['label' => 'Rejected', 'value' => $rejectedScholars, 'color' => '#ef4444'],
                ]),
            ],
            'clubs' => [
                'total' => $totalClubs,
                'chart' => $this->buildPieChart('Scholarship Clubs', [
                    ['label' => 'Registered', 'value' => $totalClubs, 'color' => '#c2410c'],
                ]),
            ],
            'completed' => [
                'total' => $completed,
                'in_progress' => $inProgress,
                'not_started' => $notStarted,
                'required' => $required,
                'completed_pct' => $completedPct,
                'chart' => $this->buildPieChart('Completed Students', [
                    ['label' => 'Completed', 'value' => $completed, 'color' => '#16a34a'],
                    ['label' => 'In progress', 'value' => $inProgress, 'color' => '#f59e0b'],
                    ['label' => 'Not started', 'value' => $notStarted, 'color' => '#94a3b8'],
                ]),
            ],
            'participation' => [
                'total' => $totalParticipation,
                'approved' => $approvedParticipation,
                'pending' => $pendingParticipation,
                'failed' => $failedParticipation,
                'rejected' => $rejectedParticipation,
                'chart' => $this->buildPieChart('Participation', [
                    ['label' => 'Approved', 'value' => $approvedParticipation, 'color' => '#16a34a'],
                    ['label' => 'Pending', 'value' => $pendingParticipation, 'color' => '#f59e0b'],
                    ['label' => 'Failed check-in', 'value' => $failedParticipation, 'color' => '#ef4444'],
                    ['label' => 'Rejected', 'value' => $rejectedParticipation, 'color' => '#94a3b8'],
                ]),
            ],
        ];
    }

    private function regionScholarsQuery(array $programIds, int $year, CarbonInterface $ayEnd): Builder
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds)
            ->where(function ($query) use ($year, $ayEnd) {
                $query->where(function ($withinYear) use ($year, $ayEnd) {
                    $withinYear->where('created_at', '<=', $ayEnd)
                        ->where(function ($academicYear) use ($year) {
                            $academicYear->where('academic_year_start', $year)
                                ->orWhereNull('academic_year_start');
                        });
                })->orWhereHas('attendances', fn ($attendance) => $attendance
                    ->where('academic_year_start', $year));
            });
    }

    /**
     * @param  array<int, array{label: string, value: int|float, color: string}>  $slices
     * @return array{title: string, total: int|float, empty: bool, gradient: string, slices: array<int, array<string, mixed>>}
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
            'empty' => $total <= 0 || $gradient === '',
            'gradient' => $gradient,
            'slices' => $formatted,
        ];
    }

    public function scholarsQuery(array $programIds, ?array $clubIds = null)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->with(['scholarshipProgram', 'scholarshipClub']);

        return $this->constrainClubs($query, $clubIds);
    }

    public function staffQuery(array $programIds, ?array $clubIds = null)
    {
        $query = User::query()
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->with(['scholarshipProgram', 'scholarshipClub']);

        return $this->constrainClubs($query, $clubIds);
    }

    public function approvedStaffQuery(array $programIds, ?array $clubIds = null)
    {
        return $this->staffQuery($programIds, $clubIds)->where('status', User::STATUS_APPROVED);
    }

    public function visibleStaffQuery(array $programIds, ?array $clubIds = null)
    {
        return $this->staffQuery($programIds, $clubIds)->where('status', '!=', User::STATUS_PENDING);
    }

    public function pendingStaffQuery(array $programIds, ?array $clubIds = null)
    {
        return $this->staffQuery($programIds, $clubIds)->where('status', User::STATUS_PENDING);
    }

    public function eventsQuery(array $programIds)
    {
        return Event::query()
            ->with('scholarshipProgram')
            ->whereIn('scholarship_program_id', $programIds ?: [0]);
    }

    public function documentsQuery(array $programIds, ?array $clubIds = null)
    {
        return Document::query()
            ->with(['user', 'documentType'])
            ->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds, $clubIds));
    }

    public function documentTypesCount(array $programIds): int
    {
        return DocumentType::query()
            ->whereIn('scholarship_program_id', $programIds ?: [0])
            ->count();
    }

    public function documentOverviewStats(array $programIds, ?array $clubIds = null): array
    {
        $query = Document::query()->whereHas('user', fn ($q) => $this->scopeScholars($q, $programIds, $clubIds));

        $total = (clone $query)->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $pending = (clone $query)->whereIn('status', ['pending', 'submitted'])->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();
        $notSubmitted = (clone $query)->where('status', 'not_submitted')->count();

        return compact('total', 'approved', 'pending', 'rejected', 'notSubmitted');
    }

    private function scopeScholars(Builder $query, array $programIds, ?array $clubIds = null): Builder
    {
        $query
            ->where('role', User::ROLE_SCHOLAR)
            ->whereIn('scholarship_program_id', $programIds ?: [0]);

        return $this->constrainClubs($query, $clubIds);
    }

    private function constrainClubs(Builder $query, ?array $clubIds): Builder
    {
        if ($clubIds !== null) {
            $query->whereIn('scholarship_club_id', $clubIds ?: [0]);
        }

        return $query;
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
        $cacheKey = 'admin.sidebar_badges.v3.'.md5(implode(',', array_map('intval', $programIds)));

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

            $reportsAttention = (int) ($completion['in_progress'] ?? 0)
                + (int) ($completion['not_started'] ?? 0);

            $badges = [
                'scholars' => $pendingScholars,
                'staff' => $pendingStaff,
                'events' => $pendingEvents,
                'service-hours' => $pendingServiceHours,
                'documents' => $pendingDocuments,
                'reports' => 0,
                'settings' => 0,
            ];

            $badges['dashboard'] = $pendingScholars
                + $pendingStaff
                + $pendingEvents
                + $pendingAttendances
                + $pendingDocuments;

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
