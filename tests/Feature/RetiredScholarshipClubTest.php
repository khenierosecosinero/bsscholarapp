<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RetiredScholarshipClubTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_club_is_removed_without_touching_other_clubs(): void
    {
        $city = $this->makeCityProgram('retired-remove');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $retired = $this->insertRetiredClub($city);
        $school = ScholarshipClubSchool::createForClub('Retired High School', $retired->id);

        $scholar = User::register([
            'full_name' => 'Retired Club Scholar',
            'scholar_id' => 'SCH-RETIRED-001',
            'email' => 'retired-club-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $retired->id,
            'scholarship_club_school_id' => $school->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $this->assertSame(1, ScholarshipClub::removeRetiredClubs());

        $this->assertDatabaseMissing('scholarship_clubs', ['id' => $retired->id]);
        $this->assertDatabaseMissing('scholarship_club_schools', ['id' => $school->id]);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keni->id,
            'name' => 'KENI SCHOLARSHIP',
        ]);

        $scholar->refresh();
        $this->assertNull($scholar->scholarship_club_id);
        $this->assertNull($scholar->scholarship_club_school_id);
        $this->assertNotSame('Batang Surigaonon Scholars Club', $scholar->scholarshipClubName());
    }

    public function test_register_and_admin_filters_hide_retired_club_and_keep_keni(): void
    {
        $city = $this->makeCityProgram('retired-lists');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        ScholarshipClubSchool::createForClub('KENI High School', $keni->id);
        $this->insertRetiredClub($city);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('KENI SCHOLARSHIP')
            ->assertDontSee('Batang Surigaonon Scholars Club');

        $options = collect(ScholarshipClub::registrationOptions())->pluck('name')->all();
        $this->assertContains('KENI SCHOLARSHIP', $options);
        $this->assertNotContains('Batang Surigaonon Scholars Club', $options);

        $filters = app(AdminDashboardService::class)->scholarshipClubFilterOptions();
        $this->assertTrue($filters->contains('name', 'KENI SCHOLARSHIP'));
        $this->assertFalse($filters->contains('name', 'Batang Surigaonon Scholars Club'));

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.scholars'))
            ->assertOk()
            ->assertSee('KENI SCHOLARSHIP')
            ->assertSee('All Scholarship Clubs')
            ->assertDontSee('Batang Surigaonon Scholars Club');
    }

    public function test_scholar_cannot_register_under_the_retired_club(): void
    {
        $city = $this->makeCityProgram('retired-register');
        $retired = $this->insertRetiredClub($city);
        $school = ScholarshipClubSchool::createForClub('Retired High School', $retired->id);

        $this->post(route('register.post'), [
            'full_name' => 'New Scholar',
            'scholar_id' => 'SNS-RETIRED-002',
            'scholarship_club_id' => $retired->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '1st Year',
            'email' => 'retired-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_id');

        $this->assertDatabaseMissing('users', ['email' => 'retired-register@example.com']);
    }

    public function test_staff_cannot_create_or_rename_to_the_retired_club(): void
    {
        $city = $this->makeCityProgram('retired-staff');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);

        $this->post(route('register.staff.post'), [
            'full_name' => 'Retired Staff',
            'scholarship_program_id' => $city->id,
            'scholarship_club_name' => 'Batang Surigaonon Scholars Club',
            'email' => 'retired-staff@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_name');

        $this->assertDatabaseMissing('users', ['email' => 'retired-staff@example.com']);
        $this->assertDatabaseMissing('scholarship_clubs', ['name' => 'Batang Surigaonon Scholars Club']);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $keni->id,
            'name' => 'KENI SCHOLARSHIP',
        ]);

        $staff = User::register([
            'full_name' => 'KENI Staff',
            'scholar_id' => 'STAFF-KENI-RETIRED',
            'email' => 'keni-retired-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $keni->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->from(route('staff.settings'))
            ->put(route('staff.settings.club'), [
                'scholarship_club_name' => 'Batang Surigaonon Scholars Club',
                'scholarship_program_id' => $city->id,
                'cellphone_number' => '09179876543',
            ])
            ->assertRedirect(route('staff.settings'))
            ->assertSessionHasErrors('scholarship_club_name');

        $this->assertSame('KENI SCHOLARSHIP', $keni->fresh()->name);

        try {
            ScholarshipClub::createForProgram('Batang Surigaonon Scholar\'s Club', $city->id);
            $this->fail('Retired club name should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scholarship_club_name', $exception->errors());
        }
    }

    public function test_admin_club_filter_still_works_for_keni(): void
    {
        $city = $this->makeCityProgram('retired-filter');
        $other = $this->makeCityProgram('retired-filter-dapa', 'Dapa', 'dapa-retired-filter');
        $keni = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $city->id);
        $dapaClub = ScholarshipClub::createForProgram('Dapa Scholars Club', $other->id);
        $this->insertRetiredClub($city);

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
            ->assertSee('KENI SCHOLARSHIP')
            ->assertDontSee('Batang Surigaonon Scholars Club');
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

    private function insertRetiredClub(ScholarshipProgram $program): ScholarshipClub
    {
        $location = $program->registrationLocation();

        return ScholarshipClub::create([
            'name' => 'Batang Surigaonon Scholars Club',
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
