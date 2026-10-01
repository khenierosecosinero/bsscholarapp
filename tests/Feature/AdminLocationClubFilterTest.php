<?php

namespace Tests\Feature;

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
            ->assertDontSee('<h3>Events</h3>', false)
            ->assertDontSee('>Locations</span>', false);
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
}
