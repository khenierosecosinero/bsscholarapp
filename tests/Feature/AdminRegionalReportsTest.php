<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use App\Services\AdminDashboardService;
use App\Services\ScholarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRegionalReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_shows_three_regions_and_academic_year_filter(): void
    {
        $html = $this->actingAs($this->makeAdmin())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Academic Year')
            ->assertSee('SURIGAO DEL NORTE')
            ->assertSee('SURIGAO DEL SUR')
            ->assertDontSee('LUZON')
            ->assertDontSee('VISAYAS')
            ->assertDontSee('MINDANAO')
            ->assertSee('Scholarship Clubs')
            ->assertSee('Completed Students (Completed Service Hours)')
            ->assertSee('Participation')
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('City Scholarship Program Data')
            ->assertDontSee('Province Scholarship Program Data')
            ->assertDontSee('Scholarship Category')
            ->assertDontSee('class="academic-period-filter"', false)
            ->assertSee('admin-reports-year', false)
            ->assertSee('Viewing Academic Year')
            ->getContent();

        $this->assertSame(8, substr_count($html, 'admin-pie-card'));
        $this->assertStringContainsString('name="year_start"', $html);
        $this->assertStringNotContainsString('aria-label="Semester"', $html);
    }

    public function test_regional_reports_use_academic_year_and_do_not_mix_island_groups(): void
    {
        $norte = $this->makeCityProgram('Dapa', 'Surigao del Norte', 'Caraga', 'dapa-regional-reports');
        $sur = $this->makeCityProgram('Tandag', 'Surigao del Sur', 'Caraga', 'tandag-regional-reports');
        $manila = $this->makeCityProgram('Quezon City', 'Metro Manila', 'National Capital Region', 'qc-regional-reports');

        ScholarshipClub::createForProgram('Dapa Scholars Club', $norte->id);
        ScholarshipClub::createForProgram('Tandag Scholars Club', $sur->id);

        $norteScholar = $this->makeScholar($norte, 'NORTE', User::STATUS_APPROVED);
        $this->makeScholar($norte, 'PENDING', User::STATUS_PENDING);
        $surScholar = $this->makeScholar($sur, 'SUR', User::STATUS_APPROVED);
        $this->makeScholar($manila, 'MANILA', User::STATUS_APPROVED);

        $this->makeAttendance($norte, $norteScholar, 2025, '1st Semester', 30);
        $this->makeAttendance($sur, $surScholar, 2026, '1st Semester', 10);

        $service = app(AdminDashboardService::class);
        $academic = app(AcademicSettingsService::class);

        $for2026 = $service->regionalReports($academic->resolveReportFilter(2026, AcademicSettingsService::SEMESTER_ALL));

        $this->assertSame(2, $for2026['surigao_del_norte']['scholars']['total']);
        $this->assertSame(1, $for2026['surigao_del_norte']['scholars']['approved']);
        $this->assertSame(1, $for2026['surigao_del_norte']['scholars']['pending']);
        $this->assertSame(1, $for2026['surigao_del_norte']['clubs']['total']);
        $this->assertSame(0, $for2026['surigao_del_norte']['completed']['total']);
        $this->assertSame(0, $for2026['surigao_del_norte']['participation']['total']);

        $this->assertSame(1, $for2026['surigao_del_sur']['scholars']['total']);
        $this->assertSame(1, $for2026['surigao_del_sur']['clubs']['total']);
        $this->assertSame(0, $for2026['surigao_del_sur']['completed']['total']);
        $this->assertSame(1, $for2026['surigao_del_sur']['completed']['in_progress']);
        $this->assertSame(1, $for2026['surigao_del_sur']['participation']['total']);

        $for2025 = $service->regionalReports($academic->resolveReportFilter(2025, AcademicSettingsService::SEMESTER_ALL));

        $this->assertSame(1, $for2025['surigao_del_norte']['scholars']['total']);
        $this->assertSame(1, $for2025['surigao_del_norte']['completed']['total']);
        $this->assertSame(1, $for2025['surigao_del_norte']['participation']['total']);
        $this->assertSame(0, $for2025['surigao_del_sur']['scholars']['total']);
        $this->assertSame(0, $for2025['surigao_del_sur']['completed']['total']);
        $this->assertSame(0, $for2025['surigao_del_sur']['participation']['total']);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.reports', ['year' => 2026]))
            ->assertOk()
            ->assertSee('AY 2026–2027')
            ->assertSee('SURIGAO DEL NORTE')
            ->assertSee('SURIGAO DEL SUR')
            ->assertDontSee('LUZON')
            ->assertDontSee('VISAYAS')
            ->assertDontSee('MINDANAO')
            ->assertSee('pie chart', false)
            ->assertDontSee('bar chart');
    }

    public function test_completed_students_require_service_hours_in_the_selected_year(): void
    {
        $mindanao = $this->makeCityProgram('Surigao City', 'Surigao del Norte', 'Caraga', 'surigao-completed-reports');
        $completed = $this->makeScholar($mindanao, 'DONE', User::STATUS_APPROVED);
        $this->makeScholar($mindanao, 'APPROVED', User::STATUS_APPROVED);

        $this->makeAttendance($mindanao, $completed, 2026, '1st Semester', ScholarService::REQUIRED_HOURS);

        $regions = app(AdminDashboardService::class)
            ->regionalReports(app(AcademicSettingsService::class)->resolveReportFilter(2026, AcademicSettingsService::SEMESTER_ALL));

        $this->assertSame(2, $regions['surigao_del_norte']['scholars']['total']);
        $this->assertSame(1, $regions['surigao_del_norte']['completed']['total']);
        $this->assertSame(1, $regions['surigao_del_norte']['completed']['not_started']);
        $this->assertSame(0, $regions['surigao_del_sur']['completed']['total']);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-REGIONAL-001',
            'email' => 'admin-regional-reports@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeCityProgram(string $city, string $province, string $region, string $slug): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $city,
            'location_type' => 'city_municipality',
            'name' => $city.', '.$province,
            'display_name' => $city.', '.$province,
            'province_name' => $province,
            'region_name' => $region,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, string $suffix, string $status): User
    {
        return User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-REG-'.$suffix,
            'email' => 'scholar-reg-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => $status,
            'scholarship_program_id' => $program->id,
            'city' => $program->location_name,
            'province' => $program->province_name,
        ]);
    }

    private function makeAttendance(
        ScholarshipProgram $program,
        User $scholar,
        int $year,
        string $semester,
        float $hours,
    ): Attendance {
        $event = Event::create([
            'title' => $program->location_name.' Service',
            'description' => 'Regional report fixture.',
            'location' => $program->location_name,
            'starts_at' => now()->setYear($year)->setMonth(9)->setDay(15)->setTime(8, 0),
            'ends_at' => now()->setYear($year)->setMonth(9)->setDay(15)->setTime(12, 0),
            'service_hours' => $hours,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $program->id,
        ]);

        return Attendance::create([
            'user_id' => $scholar->id,
            'event_id' => $event->id,
            'academic_year_start' => $year,
            'academic_year_end' => $year + 1,
            'semester' => $semester,
            'check_in' => $event->starts_at->copy()->addMinutes(5),
            'check_out' => $event->ends_at,
            'hours_earned' => $hours,
            'status' => Attendance::STATUS_APPROVED,
        ]);
    }
}
