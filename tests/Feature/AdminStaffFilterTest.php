<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Support\PhilippineIslandGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_page_is_unified_without_city_or_province_scholar_split(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('All Regions')
            ->assertSee('Luzon')
            ->assertSee('Visayas')
            ->assertSee('Mindanao')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Province')
            ->assertSee('Municipality / City')
            ->assertSee('All Scholar Staff Accounts')
            ->assertSee('Contact Number')
            ->assertDontSee('Scholarship category')
            ->assertDontSee('>City Scholar</option>', false)
            ->assertDontSee('>Province Scholar</option>', false)
            ->assertDontSee('Viewing: City Scholar')
            ->assertDontSee('City Scholar and Province Scholar stay separate')
            ->assertDontSee('Current Admin Scope')
            ->assertDontSee('Showing City Scholar records')
            ->assertDontSee('No registered Scholarship Club found')
            ->assertDontSee('Approvals are scoped to the selected City or Province')
            ->assertSee('id="staff-confirm-modal"', false)
            ->assertSee('id="admin-staff-more-modal"', false)
            ->assertDontSee("onsubmit=\"return confirm('Reject this scholar staff registration?", false);
    }

    public function test_staff_list_includes_staff_from_city_and_province_programs(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-dir', null, 'Caraga');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-staff-dir', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $city->id);

        $cityStaff = $this->makeStaff($city, $club, 'CITY');
        $provinceStaff = $this->makeStaff($province, null, 'PROV');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee($cityStaff->full_name)
            ->assertSee($provinceStaff->full_name)
            ->assertSee($cityStaff->cellphone_number)
            ->assertSee('Dapa Scholars Club')
            ->assertDontSee('Scholar Staff Number')
            ->assertSee('>View</a>', false)
            ->assertSee('>More</button>', false);
    }

    public function test_region_province_city_and_club_filters_limit_staff(): void
    {
        $sdn = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-loc', null, 'Caraga');
        $dapa = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-staff-loc', 'Surigao del Norte', 'Caraga');
        $surigao = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-staff-loc', 'Surigao del Norte', 'Caraga');
        $manila = $this->makeProgram('city_municipality', 'Manila', 'Manila, Metro Manila', 'manila-staff-loc', 'Metro Manila', 'National Capital Region');
        $this->makeProgram('province', 'Metro Manila', 'Metro Manila', 'mm-staff-loc', null, 'National Capital Region');

        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $surigao->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $dapa->id);
        $manilaClub = ScholarshipClub::createForProgram('Manila Scholars Club', $manila->id);

        $keniStaff = $this->makeStaff($surigao, $keni, 'KENI');
        $dapaStaff = $this->makeStaff($dapa, $dapaClub, 'DAPA');
        $luzonStaff = $this->makeStaff($manila, $manilaClub, 'LUZON');
        $provinceStaff = $this->makeStaff($sdn, null, 'SDN');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff', ['region' => PhilippineIslandGroup::LUZON]))
            ->assertOk()
            ->assertSee($luzonStaff->full_name)
            ->assertDontSee($keniStaff->full_name)
            ->assertDontSee($dapaStaff->full_name);

        $this->actingAs($admin)
            ->get(route('admin.staff', ['region' => PhilippineIslandGroup::MINDANAO]))
            ->assertOk()
            ->assertSee($keniStaff->full_name)
            ->assertSee($dapaStaff->full_name)
            ->assertSee($provinceStaff->full_name)
            ->assertDontSee($luzonStaff->full_name);

        $this->actingAs($admin)
            ->get(route('admin.staff', ['location' => $dapa->id]))
            ->assertOk()
            ->assertSee($dapaStaff->full_name)
            ->assertDontSee($keniStaff->full_name)
            ->assertDontSee($luzonStaff->full_name);

        $this->actingAs($admin)
            ->get(route('admin.staff', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniStaff->full_name)
            ->assertDontSee($dapaStaff->full_name)
            ->assertDontSee($luzonStaff->full_name)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('data-city="Surigao City"', false)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Dapa Scholars Club');

        $this->actingAs($admin)
            ->get(route('admin.staff', [
                'location' => $dapa->id,
                'club' => $keni->id,
            ]))
            ->assertOk()
            ->assertDontSee($keniStaff->full_name)
            ->assertDontSee($dapaStaff->full_name)
            ->assertSee('No records found');
    }

    public function test_staff_search_works_with_filters_and_approve_does_not_require_program_type(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-staff-search', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $pending = $this->makeStaff($city, $club, 'PEND', User::STATUS_PENDING);
        $approved = $this->makeStaff($city, $club, 'OK');

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff'))
            ->assertOk()
            ->assertSee('data-confirm-title="Reject this scholar staff registration?"', false)
            ->assertSee('data-no-loading="true"', false)
            ->assertSee('data-confirm="The account will be marked as Rejected and will not be able to log in."', false)
            ->assertSee('data-confirm-yes="Reject"', false)
            ->assertSee('data-confirm-no="Cancel"', false);

        $this->actingAs($admin)
            ->get(route('admin.staff', ['search' => $approved->cellphone_number]))
            ->assertOk()
            ->assertSee($approved->full_name)
            ->assertSee($approved->cellphone_number)
            ->assertDontSee($pending->full_name);

        $this->actingAs($admin)
            ->post(route('admin.staff.approve', $pending), [
                'location' => 'all',
            ])
            ->assertRedirect();

        $this->assertSame(User::STATUS_APPROVED, $pending->fresh()->status);
    }

    public function test_admin_can_view_staff_information_without_scholar_staff_number(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-staff-show', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $staff = $this->makeStaff($city, $club, 'VIEW');
        $scholar = User::register([
            'full_name' => 'Not Staff',
            'scholar_id' => 'SCH-NOT-STAFF-001',
            'email' => 'not-staff-dir@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staff))
            ->assertOk()
            ->assertSee('Staff Information')
            ->assertSee($staff->full_name)
            ->assertSee($staff->email)
            ->assertSee($staff->cellphone_number)
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Surigao City')
            ->assertSee('Surigao del Norte')
            ->assertSee('Contact Number')
            ->assertDontSee('Scholar Staff Number')
            ->assertDontSee($staff->scholar_id);

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $scholar))
            ->assertNotFound();
    }

    public function test_admin_can_activate_deactivate_and_delete_staff_accounts(): void
    {
        $city = $this->makeProgram('city_municipality', 'Surigao City', 'Surigao City, Surigao del Norte', 'sc-staff-more', 'Surigao del Norte', 'Caraga');
        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $approved = $this->makeStaff($city, $club, 'ACT');
        $pending = $this->makeStaff($city, $club, 'WAIT', User::STATUS_PENDING);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.staff', ['location' => 'all']))
            ->assertOk()
            ->assertSee('>More</button>', false)
            ->assertSee($approved->full_name);

        $this->actingAs($admin)
            ->post(route('admin.staff.deactivate', $approved), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_INACTIVE, $approved->fresh()->status);

        $this->actingAs($approved->fresh())
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->post(route('admin.staff.activate', $approved), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_APPROVED, $approved->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.staff.deactivate', $pending), ['location' => 'all'])
            ->assertStatus(422);

        $deleteEmail = $approved->email;
        $this->actingAs($admin)
            ->delete(route('admin.staff.delete', $approved), ['location' => 'all'])
            ->assertRedirect(route('admin.staff', ['location' => 'all']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => $deleteEmail]);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-STAFF-DIR-001',
            'email' => 'admin-staff-dir@example.com',
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

    private function makeStaff(
        ScholarshipProgram $program,
        ?ScholarshipClub $club,
        string $suffix,
        string $status = User::STATUS_APPROVED,
    ): User {
        return User::register([
            'full_name' => 'Staff '.$suffix,
            'email' => 'staff-dir-'.strtolower($suffix).'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => $status,
            'cellphone_number' => '0917'.substr(md5($suffix), 0, 7),
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club?->id,
            'city' => $program->isCityOrMunicipality() ? $program->location_name : null,
            'province' => $program->isProvince() ? $program->location_name : $program->province_name,
        ])->fresh();
    }
}
