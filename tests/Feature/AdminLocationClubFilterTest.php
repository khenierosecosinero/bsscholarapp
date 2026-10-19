<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocationClubFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-CLUB-001',
            'email' => 'admin-club-filter@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeProgram(string $type, string $location, string $display, string $slug, ?string $province = null): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $location,
            'location_type' => $type,
            'name' => $display,
            'display_name' => $display,
            'province_name' => $province,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, ?ScholarshipClub $club, string $suffix): User
    {
        return User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-'.$suffix,
            'email' => 'scholar-'.$suffix.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ]);
    }

    public function test_admin_dashboard_uses_province_and_city_without_category_or_club_list(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-admin-clubs');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-admin-clubs', 'Surigao del Norte');
        $tandag = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-admin-clubs', 'Surigao del Sur');
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-admin-clubs');

        ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        ScholarshipClub::createForProgram('Tandag Scholars Club', $tandag->id);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', [
                'location' => $dapa->id,
            ]))
            ->assertOk()
            ->assertSee('Location')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertSee('Total Scholarship Clubs')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Scholarship category')
            ->assertDontSee('name="program_type"', false)
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Dapa Scholars Club')
            ->assertDontSee('Tandag Scholars Club')
            ->assertDontSee('No registered Scholarship Club found')
            ->assertSee('<h3>Events</h3>', false)
            ->assertSee('Total completed events')
            ->assertDontSee('Completed Events')
            ->assertSee('Overall Reports')
            ->assertSee('View Overall Reports')
            ->assertDontSee('>Locations</span>', false)
            ->assertDontSee('admin-location-pill', false)
            ->assertDontSee('City Scholar — All Locations')
            ->assertDontSee('📍');
    }

    public function test_admin_dashboard_stats_follow_selected_province_and_city_only(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-dash-stats');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-dash-stats', 'Surigao del Norte');
        $tandag = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-dash-stats', 'Surigao del Sur');
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-dash-stats');

        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        $tandagClub = ScholarshipClub::createForProgram('Tandag Scholars Club', $tandag->id);

        $this->makeScholar($dapa, $dapaClub, 'DAPA');
        $this->makeScholar($tandag, $tandagClub, 'TANDAG');

        $admin = $this->makeAdmin();
        $service = app(AdminDashboardService::class);

        $cityStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $dapa->id),
            null,
            $service->geographicClubs((string) $dapa->id)->count()
        );
        $this->assertSame(1, $cityStats['total_scholars']);
        $this->assertSame(1, $cityStats['total_clubs']);

        $provinceStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $sdn->id),
            null,
            $service->geographicClubs((string) $sdn->id)->count()
        );
        $this->assertSame(1, $provinceStats['total_scholars']);
        $this->assertSame(1, $provinceStats['total_clubs']);

        $emptyCity = $this->makeProgram('city_municipality', 'Del Carmen', 'Del Carmen, Surigao del Norte', 'del-carmen-dash-stats', 'Surigao del Norte');
        $emptyStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $emptyCity->id),
            null,
            $service->geographicClubs((string) $emptyCity->id)->count()
        );
        $this->assertSame(0, $emptyStats['total_scholars']);
        $this->assertSame(0, $emptyStats['total_clubs']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['location' => $emptyCity->id]))
            ->assertOk()
            ->assertSee('Total Scholarship Clubs')
            ->assertDontSee('Dapa Scholars Club');
    }

    public function test_admin_dashboard_events_card_counts_completed_events_by_location(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-dash-events');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-dash-events', 'Surigao del Norte');
        $tandag = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-dash-events', 'Surigao del Sur');

        Event::create([
            'title' => 'Dapa Completed Event',
            'description' => 'Completed in Dapa',
            'location' => 'Dapa',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(2),
            'service_hours' => 4,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $dapa->id,
        ]);
        Event::create([
            'title' => 'Dapa Ongoing Event',
            'description' => 'Not completed',
            'location' => 'Dapa',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'service_hours' => 2,
            'status' => Event::STATUS_ONGOING,
            'scholarship_program_id' => $dapa->id,
        ]);
        Event::create([
            'title' => 'Tandag Completed Event',
            'description' => 'Completed in Tandag',
            'location' => 'Tandag',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(4),
            'service_hours' => 3,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $tandag->id,
        ]);

        $service = app(AdminDashboardService::class);

        $cityStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $dapa->id),
            null,
            $service->geographicClubs((string) $dapa->id)->count()
        );
        $this->assertSame(1, (int) $cityStats['completed_events']);

        $provinceStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $sdn->id),
            null,
            $service->geographicClubs((string) $sdn->id)->count()
        );
        $this->assertSame(1, (int) $provinceStats['completed_events']);

        $allStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds('all'),
            null,
            $service->geographicClubs('all')->count()
        );
        $this->assertSame(2, (int) $allStats['completed_events']);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee('<h3>Events</h3>', false)
            ->assertSee('Total completed events')
            ->assertDontSee('Completed Events');
    }

    public function test_overall_reports_card_ignores_dashboard_location_filter(): void
    {
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-overall-reports', 'Surigao del Norte');
        $tandag = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-overall-reports', 'Surigao del Sur');
        $dapaClub = ScholarshipClub::createForProgram('Dapa Overall Club', $dapa->id);
        $tandagClub = ScholarshipClub::createForProgram('Tandag Overall Club', $tandag->id);

        $this->makeScholar($dapa, $dapaClub, 'OVERALL-DAPA');
        $this->makeScholar($tandag, $tandagClub, 'OVERALL-TANDAG');

        User::register([
            'full_name' => 'Staff Overall',
            'scholar_id' => 'STAFF-OVERALL-1',
            'email' => 'staff-overall@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $dapa->id,
            'scholarship_club_id' => $dapaClub->id,
        ]);

        Event::create([
            'title' => 'Overall Event One',
            'description' => 'Dapa event',
            'location' => 'Dapa',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'service_hours' => 2,
            'status' => Event::STATUS_COMPLETED,
            'scholarship_program_id' => $dapa->id,
        ]);
        Event::create([
            'title' => 'Overall Event Two',
            'description' => 'Tandag event',
            'location' => 'Tandag',
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subDays(3),
            'service_hours' => 2,
            'status' => Event::STATUS_CONFIRMED,
            'scholarship_program_id' => $tandag->id,
        ]);

        $service = app(AdminDashboardService::class);
        $summary = $service->overallReportSummary();

        $this->assertSame(2, (int) $summary['total_scholars']);
        $this->assertSame(1, (int) $summary['total_staff']);
        $this->assertFalse($summary['chart']['empty']);
        $this->assertCount(2, $summary['chart']['slices']);
        $this->assertSame('Total Scholars', $summary['chart']['slices'][0]['label']);
        $this->assertSame('Total Scholar Staff', $summary['chart']['slices'][1]['label']);
        $this->assertSame(2.0, (float) $summary['chart']['slices'][0]['value']);
        $this->assertSame(1.0, (float) $summary['chart']['slices'][1]['value']);
        $this->assertStringContainsString('conic-gradient', (string) $summary['chart']['gradient']);

        $cityStats = $service->dashboardStats(
            $service->resolveGeographicProgramIds((string) $dapa->id),
            null,
            $service->geographicClubs((string) $dapa->id)->count()
        );
        $this->assertSame(1, (int) $cityStats['total_scholars']);
        $this->assertSame(1, (int) $cityStats['completed_events']);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee('Overall Reports')
            ->assertSee('View Overall Reports')
            ->assertSee('Total Scholars')
            ->assertSee('Total Scholar Staff')
            ->assertSee('admin-pie-chart', false)
            ->assertDontSee('Total Events')
            ->assertDontSee('admin-overall-reports-list', false)
            ->assertSee('href="'.route('admin.reports').'"', false);
    }

    public function test_admin_locations_page_and_sidebar_item_are_removed(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin/locations')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Locations</span>', false)
            ->assertSee('>Dashboard</span>', false)
            ->assertSee('>Scholars</span>', false);

        $this->actingAs($admin)
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertDontSee('Scholarship category')
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('Current Admin Scope');
    }

    public function test_admin_pages_do_not_show_the_location_scope_pill(): void
    {
        $admin = $this->makeAdmin();

        foreach ([
            'admin.dashboard',
            'admin.scholars',
            'admin.staff',
            'admin.events',
            'admin.service-hours',
            'admin.documents',
            'admin.reports',
            'admin.settings',
        ] as $route) {
            $html = $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('admin-location-pill', $html);
            $this->assertStringNotContainsString('City Scholar — All Locations', $html);
            $this->assertStringNotContainsString('Overall / All Locations', $html);
        }
    }
}
