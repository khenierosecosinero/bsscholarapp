<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipClubRegistrationTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeClub(ScholarshipProgram $program, string $name, ?User $staff = null): ScholarshipClub
    {
        $location = $program->registrationLocation();

        return ScholarshipClub::create([
            'name' => $name,
            'city' => $location['city'],
            'province' => $location['province'],
            'scholarship_program_id' => $program->id,
            'created_by' => $staff?->id,
            'is_active' => true,
        ]);
    }

    public function test_register_page_shows_scholarship_club_and_readonly_address(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-page', 'Surigao del Norte');
        $this->makeClub($city, 'Dapa Scholars Club');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Scholarship Club')
            ->assertSee('name="scholarship_club_id"', false)
            ->assertSee('Dapa Scholars Club')
            ->assertSee('data-city="Dapa"', false)
            ->assertSee('data-province="Surigao del Norte"', false)
            ->assertSee('name="scholarship_club_school_id"', false)
            ->assertSee('name="course_year_level"', false)
            ->assertSee('placeholder="Bachelor of Science in Information Technology"', false)
            ->assertDontSee('<select class="form-input form-select" id="course_year_level"', false)
            ->assertSee('Do not use initials such as BSICT')
            ->assertDontSee('value="BSICT"', false)

            ->assertSee('1st Year')
            ->assertSee('2nd Year')
            ->assertSee('3rd Year')
            ->assertSee('4th Year')
            ->assertSee('name="year_level"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="scholarship_program_id"', false);
    }

    public function test_staff_registration_saves_typed_club_name_and_address(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-reg');
        $program = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-staff-reg', 'Surigao del Norte');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Club Staff',
            'scholar_id' => 'STAFF-CLUB-REG',
            'scholarship_program_id' => $program->id,
            'scholarship_club_name' => 'Dapa Scholars Club',
            'email' => 'club-staff-reg@example.com',
            'cellphone_number' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $staff = User::query()->where('email', 'club-staff-reg@example.com')->first();

        $this->assertNotNull($staff);
        $this->assertSame(User::ROLE_SCHOLAR_STAFF, $staff->role);
        $this->assertSame($program->id, $staff->scholarship_program_id);
        $this->assertSame('Dapa', $staff->city);
        $this->assertSame('Surigao del Norte', $staff->province);
        $this->assertNotNull($staff->scholarship_club_id);
        $this->assertSame('09171234567', $staff->cellphone_number);
        $this->assertSame('Dapa Scholars Club', $staff->scholarshipClubName());
        $this->assertSame('Dapa, Surigao del Norte', $staff->scholarshipClubAddress());
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $staff->scholarship_club_id,
            'name' => 'Dapa Scholars Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'created_by' => $staff->id,
        ]);
    }

    public function test_staff_registration_rejects_a_province_program_as_club_address(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-staff-reject');

        $this->post(route('register.staff.post'), [
            'full_name' => 'Club Staff',
            'scholar_id' => 'STAFF-CLUB-PROV',
            'scholarship_program_id' => $province->id,
            'scholarship_club_name' => 'Province Club',
            'email' => 'province-club@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_program_id');

        $this->assertDatabaseMissing('users', ['email' => 'province-club@example.com']);
        $this->assertDatabaseMissing('scholarship_clubs', ['name' => 'Province Club']);
    }

    public function test_scholar_registration_saves_selected_club_and_its_address(): void
    {
        $province = $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte Club', 'sdn-club-save');
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-save', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Scholars Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Club Scholar',
            'scholar_id' => 'SNS-CLUB-001',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'email' => 'club-scholar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $scholar = User::query()->where('email', 'club-scholar@example.com')->first();

        $this->assertNotNull($scholar);
        $this->assertSame(User::ROLE_SCHOLAR, $scholar->role);
        $this->assertSame($city->id, $scholar->scholarship_program_id);
        $this->assertSame($club->id, $scholar->scholarship_club_id);
        $this->assertSame('Dapa', $scholar->city);
        $this->assertSame('Surigao del Norte', $scholar->province);
        $this->assertSame($school->id, $scholar->scholarship_club_school_id);
        $this->assertSame('Dapa National High School', $scholar->school_university);
        $this->assertSame('Bachelor of Science in Information Technology', $scholar->course_year_level);
        $this->assertSame('2nd Year', $scholar->year_level);
        $this->assertSame('Dapa Scholars Club', $scholar->scholarshipClubName());
        $this->assertSame('Dapa', $scholar->scholarshipClubCity());
        $this->assertSame('Surigao del Norte', $scholar->scholarshipClubProvince());
        $this->assertNotSame($province->id, $scholar->scholarship_program_id);
    }

    public function test_scholar_registration_requires_a_scholarship_club(): void
    {
        $this->post(route('register.post'), [
            'full_name' => 'No Club Scholar',
            'scholar_id' => 'SNS-CLUB-002',
            'email' => 'no-club@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_id');

        $this->assertDatabaseMissing('users', ['email' => 'no-club@example.com']);
    }

    public function test_scholar_registration_rejects_course_initials(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-course', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Course Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        foreach (['BSICT', 'BSCE', 'BSIS'] as $index => $initials) {
            $this->post(route('register.post'), [
                'full_name' => 'Initials Scholar',
                'scholar_id' => 'SNS-CLUB-COURSE-00'.$index,
                'scholarship_club_id' => $club->id,
                'scholarship_club_school_id' => $school->id,
                'course_year_level' => $initials,
                'email' => 'course-initials-'.$index.'@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasErrors('course_year_level');

            $this->assertDatabaseMissing('users', ['email' => 'course-initials-'.$index.'@example.com']);
        }
    }

    public function test_profile_shows_registered_course_and_year_level(): void
    {
        $city = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-profile-course', 'Surigao del Norte');
        $club = $this->makeClub($city, 'Dapa Profile Club');
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $this->post(route('register.post'), [
            'full_name' => 'Profile Course Scholar',
            'scholar_id' => 'SNS-CLUB-COURSE-002',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'email' => 'profile-course@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $scholar = User::query()->where('email', 'profile-course@example.com')->first();
        $this->assertNotNull($scholar);
        $scholar->status = User::STATUS_APPROVED;
        $scholar->save();

        $this->actingAs($scholar)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('Bachelor of Science in Information Technology')
            ->assertSee('2nd Year')
            ->assertDontSee('BSICT')
            ->assertDontSee('name="course_year_level"', false)
            ->assertDontSee('name="year_level"', false);

        $this->actingAs($scholar)
            ->put(route('user.profile.update'), [
                'full_name' => 'Profile Course Scholar',
                'course_year_level' => 'BSICT',
                'year_level' => '5th Year',
            ])
            ->assertRedirect();

        $scholar->refresh();
        $this->assertSame('Bachelor of Science in Information Technology', $scholar->course_year_level);
        $this->assertSame('2nd Year', $scholar->year_level);
    }

    public function test_staff_settings_updates_club_name_address_and_contact(): void
    {
        $this->makeProgram('province', 'Surigao del Norte', 'Surigao del Norte', 'sdn-club-settings');
        $this->makeProgram('province', 'Surigao del Sur', 'Surigao del Sur', 'sds-club-settings');
        $program = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-settings', 'Surigao del Norte');
        $nextCity = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-settings', 'Surigao del Sur');
        $club = $this->makeClub($program, 'Dapa Scholars Club');

        $staff = User::register([
            'full_name' => 'Club Staff',
            'scholar_id' => 'STAFF-CLUB-001',
            'email' => 'club-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $club->created_by = $staff->id;
        $club->save();

        $this->assertSame('STAFF-CLUB-001', $staff->contactNumber());

        $scholar = User::register([
            'full_name' => 'Club Scholar',
            'scholar_id' => 'SCH-CLUB-SETTINGS',
            'email' => 'club-scholar-settings@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.settings'))
            ->assertOk()
            ->assertSee('value="Dapa Scholars Club"', false)
            ->assertSee('name="scholarship_program_id"', false)
            ->assertSee('name="cellphone_number"', false)
            ->assertSee('value="STAFF-CLUB-001"', false)
            ->assertSee('Scholarship Club linked to this staff account');

        $this->actingAs($staff)
            ->put(route('staff.settings.club'), [
                'scholarship_club_name' => 'Tandag Youth Scholars',
                'scholarship_program_id' => $nextCity->id,
                'cellphone_number' => '09179876543',
            ])
            ->assertRedirect();

        $staff = $staff->fresh(['scholarshipClub']);
        $this->assertSame('Tandag Youth Scholars', $staff->scholarshipClubName());
        $this->assertSame('Tandag, Surigao del Sur', $staff->scholarshipClubAddress());
        $this->assertSame($nextCity->id, $staff->scholarship_program_id);
        $this->assertSame('Tandag', $staff->city);
        $this->assertSame('Surigao del Sur', $staff->province);
        $this->assertSame('09179876543', $staff->cellphone_number);
        $this->assertDatabaseHas('scholarship_clubs', [
            'id' => $club->id,
            'name' => 'Tandag Youth Scholars',
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
            'scholarship_program_id' => $nextCity->id,
        ]);
        $this->assertSame($nextCity->id, $scholar->fresh()->scholarship_program_id);
        $this->assertSame($club->id, $scholar->fresh()->scholarship_club_id);
    }

    public function test_staff_only_manages_scholars_in_their_scholarship_club(): void
    {
        $programA = $this->makeProgram('city_municipality', 'Dapa', 'Dapa, Surigao del Norte', 'dapa-club-scope', 'Surigao del Norte');
        $programB = $this->makeProgram('city_municipality', 'Tandag', 'Tandag, Surigao del Sur', 'tandag-club-scope', 'Surigao del Sur');
        $clubA = $this->makeClub($programA, 'Dapa Scholars Club');
        $clubB = $this->makeClub($programB, 'Tandag Scholars Club');

        $staffA = User::register([
            'full_name' => 'Dapa Staff',
            'scholar_id' => 'STAFF-CLUB-A',
            'email' => 'dapa-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programA->id,
            'scholarship_club_id' => $clubA->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholarA = User::register([
            'full_name' => 'Dapa Scholar',
            'scholar_id' => 'SCH-CLUB-A',
            'email' => 'dapa-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programA->id,
            'scholarship_club_id' => $clubA->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $scholarB = User::register([
            'full_name' => 'Tandag Scholar',
            'scholar_id' => 'SCH-CLUB-B',
            'email' => 'tandag-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $programB->id,
            'scholarship_club_id' => $clubB->id,
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
        ]);

        $this->assertTrue($staffA->canManageScholar($scholarA));
        $this->assertFalse($staffA->canManageScholar($scholarB));

        $this->actingAs($staffA)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertSee('Dapa Scholar')
            ->assertSee('Dapa')
            ->assertSee('Surigao del Norte')
            ->assertDontSee('Tandag Scholar');

        $this->actingAs($staffA)
            ->get(route('staff.scholars.show', $scholarB))
            ->assertForbidden();
    }
}
