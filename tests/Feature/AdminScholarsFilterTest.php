<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScholarsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholars_page_is_unified_without_city_or_province_scholar_split(): void
    {
        $html = $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertSee('Scholarship Club')
            ->assertSee('Course')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertDontSee('Scholarship category')
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('City Scholar and Province Scholar stay separate')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('>Scholar Program</th>', false)
            ->assertDontSee('>Program Type</th>', false)
            ->getContent();

        $this->assertStringContainsString('>Scholarship Club</th>', $html);
        $this->assertStringContainsString('>Course</th>', $html);
    }

    public function test_scholars_list_includes_city_and_province_program_scholars(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-scholars-dir', null);
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-scholars-dir', 'Surigao del Norte');
        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $city->id);

        $cityScholar = $this->makeScholar($city, $club, 'CITY', 'Bachelor of Science in Information Technology');
        $provinceScholar = $this->makeScholar($province, null, 'PROV', 'Bachelor of Science in Civil Engineering');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertSee($cityScholar->full_name)
            ->assertSee($provinceScholar->full_name)
            ->assertSee('Dapa Scholars Club')
            ->assertSee('Bachelor of Science in Information Technology')
            ->assertSee('Bachelor of Science in Civil Engineering');
    }

    public function test_province_and_city_filters_limit_scholars_without_program_type(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-scholars-loc', null);
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-scholars-loc', 'Surigao del Norte');
        $tandag = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-scholars-loc', 'Surigao del Sur');

        $dapaClub = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $dapa->id);
        $tandagClub = ScholarshipClub::createForProgram('Tandag Scholars Club', $tandag->id);

        $dapaScholar = $this->makeScholar($dapa, $dapaClub, 'DAPA');
        $provinceScholar = $this->makeScholar($sdn, null, 'SDN');
        $tandagScholar = $this->makeScholar($tandag, $tandagClub, 'TANDAG');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.scholars', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee($dapaScholar->scholar_id)
            ->assertDontSee($tandagScholar->scholar_id)
            ->assertDontSee($provinceScholar->scholar_id);

        $this->actingAs($admin)
            ->get(route('admin.scholars', ['location' => $sdn->id]))
            ->assertOk()
            ->assertSee($dapaScholar->scholar_id)
            ->assertSee($provinceScholar->scholar_id)
            ->assertDontSee($tandagScholar->scholar_id);
    }

    public function test_scholarship_club_filter_and_search_work_together(): void
    {
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-scholars-club', 'Surigao del Norte');
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-scholars-club', 'Surigao del Norte');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);

        $keniScholar = $this->makeScholar($city, $keni, 'KENI');
        $otherKeni = $this->makeScholar($city, $keni, 'ALSO');
        $dapaScholar = $this->makeScholar($dapa, $dapaClub, 'DAPA');

        $directory = app(AdminDashboardService::class);
        $this->assertEqualsCanonicalizing(
            [$keniScholar->id, $otherKeni->id],
            $directory->scholarDirectoryQuery('all', $keni->id)->pluck('id')->all()
        );
        $this->assertSame(
            [],
            $directory->scholarDirectoryQuery((string) $dapa->id, $keni->id)->pluck('id')->all()
        );

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.scholars', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniScholar->scholar_id)
            ->assertSee($otherKeni->scholar_id)
            ->assertDontSee($dapaScholar->scholar_id)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Dapa Scholars Club');

        $this->actingAs($admin)
            ->get(route('admin.scholars', [
                'club' => $keni->id,
                'search' => $keniScholar->scholar_id,
            ]))
            ->assertOk()
            ->assertSee($keniScholar->scholar_id)
            ->assertDontSee($otherKeni->scholar_id)
            ->assertDontSee($dapaScholar->scholar_id);

        $this->actingAs($admin)
            ->get(route('admin.scholars', [
                'location' => $dapa->id,
                'club' => $keni->id,
            ]))
            ->assertOk()
            ->assertDontSee($keniScholar->scholar_id)
            ->assertDontSee($otherKeni->scholar_id)
            ->assertDontSee($dapaScholar->scholar_id)
            ->assertSee('No scholars found.');
    }

    public function test_scholars_filter_uses_responsive_grid_and_keeps_placeholders(): void
    {
        $html = $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('admin-filter-form', $html);
        $this->assertStringContainsString('Select Province', $html);
        $this->assertStringContainsString('Select municipality or city', $html);
        $this->assertStringContainsString('All Scholarship Clubs', $html);
        $this->assertStringContainsString('>Apply</button>', $html);
        $this->assertStringContainsString('data-admin-location-tree', $html);
        $this->assertStringContainsString('data-admin-province', $html);
        $this->assertStringContainsString('data-admin-city', $html);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-SCHOLARS-DIR-001',
            'email' => 'admin-scholars-dir@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeProgram(string $type, string $location, string $display, string $slug, ?string $province): ScholarshipProgram
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

    private function makeScholar(
        ScholarshipProgram $program,
        ?ScholarshipClub $club,
        string $suffix,
        ?string $course = null,
    ): User {
        $scholar = User::register([
            'full_name' => 'Scholar '.$suffix,
            'scholar_id' => 'SCH-DIR-'.$suffix,
            'email' => 'scholar-dir-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ]);

        if ($course !== null) {
            $scholar->forceFill(['course_year_level' => $course])->save();
        }

        return $scholar->fresh();
    }
}
