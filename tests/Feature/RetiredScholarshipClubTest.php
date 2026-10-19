<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetiredScholarshipClubTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_retired_clubs_is_a_no_op_when_no_names_are_retired(): void
    {
        $city = $this->makeCityProgram('retired-remove');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $legacy = $this->insertNamedClub($city, 'Batang Surigaonon Scholars Club');
        $school = ScholarshipClubSchool::createForClub('Legacy High School', $legacy->id);

        User::register([
            'full_name' => 'Legacy Club Scholar',
            'scholar_id' => 'SCH-LEGACY-001',
            'email' => 'legacy-club-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $legacy->id,
            'scholarship_club_school_id' => $school->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $this->assertSame(0, ScholarshipClub::removeRetiredClubs());

        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $legacy->id,
            'name' => 'Batang Surigaonon Scholars Club',
            'name_key' => 'batang surigaonon scholars club',
        ]);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keni->id,
            'name' => 'KENI SCHOLARSHIP',
        ]);
        $this->assertDatabaseHas('scholarship_club_schools', ['id' => $school->id]);
    }

    public function test_register_and_admin_filters_include_any_registered_club_name(): void
    {
        $city = $this->makeCityProgram('retired-lists');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        ScholarshipClubSchool::createForClub('KENI High School', $keni->id);
        $this->insertNamedClub($city, 'Batang Surigaonon Scholars Club');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('Batang Surigaonon Scholars Club');

        $options = collect(ScholarshipClub::registrationOptions())->pluck('name')->all();
        $this->assertContains('KENI SCHOLARSHIP', $options);
        $this->assertContains('Batang Surigaonon Scholars Club', $options);

        $filters = app(AdminDashboardService::class)->scholarshipClubFilterOptions();
        $this->assertTrue($filters->contains('name', 'KENI SCHOLARSHIP'));
        $this->assertTrue($filters->contains('name', 'Batang Surigaonon Scholars Club'));

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('All Scholarship Clubs')
            ->assertSee('Batang Surigaonon Scholars Club');
    }

    public function test_staff_can_create_any_unique_club_name_including_formerly_restricted_names(): void
    {
        $city = $this->makeCityProgram('retired-staff');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Legacy Named Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Batang Surigaonon Scholars Club',
            'email' => 'legacy-named-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['email' => 'legacy-named-staff@example.com']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'name' => 'Batang Surigaonon Scholars Club',
            'name_key' => 'batang surigaonon scholars club',
        ]);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keni->id,
            'name' => 'KENI SCHOLARSHIP',
        ]);
    }

    public function test_admin_club_filter_still_works_for_keni(): void
    {
        $city = $this->makeCityProgram('retired-filter');
        $other = $this->makeCityProgram('retired-filter-dapa', 'Dapa', 'dapa-retired-filter');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $other->id);
        $this->insertNamedClub($city, 'Batang Surigaonon Scholars Club');

        $keniScholar = User::register([
            'full_name' => 'KENI Scholar',
            'scholar_id' => 'SCH-KENI-KEEP',
            'email' => 'keni-keep@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $keni->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $dapaScholar = User::register([
            'full_name' => 'Dapa Scholar',
            'scholar_id' => 'SCH-DAPA-KEEP',
            'email' => 'dapa-keep@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $other->id,
            'scholarship_club_id' => $dapaClub->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $directory = app(AdminDashboardService::class);
        $this->assertSame(
            [$keniScholar->id],
            $directory->scholarDirectoryQuery('all', $keni->id)->pluck('id')->all()
        );

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars', ['club' => $keni->id]))
            ->assertOk()
            ->assertSee($keniScholar->scholar_id)
            ->assertDontSee($dapaScholar->scholar_id)
            ->assertSee('KENI SCHOLARSHIP');
    }

    private function makeCityProgram(string $slug, string $city = 'Surigao City', string $slugSuffix = ''): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => $city,
            'location_type' => 'city_municipality',
            'name' => $city.', Surigao del Norte',
            'display_name' => $city.', Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => $slugSuffix !== '' ? $slugSuffix : $slug,
            'is_active' => true,
        ]);
    }

    private function insertNamedClub(ScholarshipProgram $program, string $name): ScholarshipClub
    {
        $location = $program->registrationLocation();

        return ScholarshipClub::create([
            'name' => $name,
            'city' => $location['city'],
            'province' => $location['province'],
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-RETIRED-001',
            'email' => 'admin-retired-club@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
