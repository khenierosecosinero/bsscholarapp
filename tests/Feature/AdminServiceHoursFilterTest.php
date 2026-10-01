<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Support\PhilippineIslandGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceHoursFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_hours_page_is_unified_without_city_or_province_scholar_split(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.service-hours'))
            ->assertOk()
            ->assertSee('All Regions')
            ->assertSee('Luzon')
            ->assertSee('Visayas')
            ->assertSee('Mindanao')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertSee('Scholar Service Hours')
            ->assertSee('All Locations')
            ->assertDontSee('Scholarship category')
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('City Scholar and Province Scholar stay separate')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('City Scholar — All Locations')
            ->assertDontSee('No registered Scholarship Club found')
            ->assertDontSee('Track service hours for the selected City or Province Scholarship Program scope.')
            ->assertDontSee('>Scholar Program</th>', false)
            ->assertDontSee('>Program Type</th>', false)
            ->assertSee('No records found');
    }

    public function test_service_hours_list_includes_city_and_province_program_scholars(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-hours-dir', null, 'Caraga');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-hours-dir', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $city->id);

        $cityScholar = $this->makeScholar($city, $club, 'CITY');
        $provinceScholar = $this->makeScholar($province, null, 'PROV');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.service-hours'))
            ->assertOk()
            ->assertSee($cityScholar->full_name)
            ->assertSee($provinceScholar->full_name)
            ->assertSee('Dapa Scholars Club');
    }

    public function test_region_province_city_and_club_filters_limit_service_hours(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-hours-loc', null, 'Caraga');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-hours-loc', 'Surigao del Norte', 'Caraga');
        $surigao = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-hours-loc', 'Surigao del Norte', 'Caraga');
        $manila = $this->makeProgram('city_municipality', 'Manila', 'Manila, Metro Manila', 'manila-hours-loc', 'Metro Manila', 'National Capital Region');
        $this->makeProgram('province', 'Metro Manila', 'Metro Manila', 'mm-hours-loc', null, 'National Capital Region');

        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $surigao->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        $manilaClub = ScholarshipClub::createForProgram('Manila Scholars Club', $manila->id);

        $keniScholar = $this->makeScholar($surigao, $keni, 'KENI');
        $dapaScholar = $this->makeScholar($dapa, $dapaClub, 'DAPA');
        $luzonScholar = $this->makeScholar($manila, $manilaClub, 'LUZON');
        $provinceScholar = $this->makeScholar($sdn, null, 'SDN');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.service-hours', ['region' => PhilippineIslandGroup::LUZON]))
            ->assertOk()
            ->assertSee($luzonScholar->full_name)
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.service-hours', ['region' => PhilippineIslandGroup::MINDANAO]))
            ->assertOk()
            ->assertSee($keniScholar->full_name)
            ->assertSee($dapaScholar->full_name)
            ->assertSee($provinceScholar->full_name)
            ->assertDontSee($luzonScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.service-hours', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee($dapaScholar->full_name)
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($luzonScholar->full_name);

        $this->actingAs($admin)
            ->get(route('admin.service-hours', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name)
            ->assertDontSee($luzonScholar->full_name)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('data-city="Surigao City"', false)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Dapa Scholars Club');

        $this->actingAs($admin)
            ->get(route('admin.service-hours', [
                'location' => $dapa->id,
                'club' => $keni->id,
            ]))
            ->assertOk()
            ->assertDontSee($keniScholar->full_name)
            ->assertDontSee($dapaScholar->full_name)
            ->assertSee('No records found');
    }

    public function test_service_hours_search_works_with_filters(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-hours-search', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $match = $this->makeScholar($city, $club, 'MATCH');
        $other = $this->makeScholar($city, $club, 'OTHER');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.service-hours', ['search' => $match->scholar_id]))
            ->assertOk()
            ->assertSee($match->full_name)
            ->assertDontSee($other->full_name);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-HOURS-DIR-001',
            'email' => 'admin-hours-dir@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeProgram(
        string $type,
        string $location,
        string $display,
        string $slug,
        ?string $province,
        ?string $region = null,
    ): ScholarshipProgram {
        return ScholarshipProgram::create([
            'location_name' => $location,
            'location_type' => $type,
            'name' => $display,
            'display_name' => $display,
            'province_name' => $province,
            'region_name' => $region,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, ?ScholarshipClub $club, string $suffix): User
    {
        return User::register([
            'full_name' => 'Hours Scholar '.$suffix,
            'scholar_id' => 'SCH-HRS-'.$suffix,
            'email' => 'hours-scholar-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ]);
    }
}
