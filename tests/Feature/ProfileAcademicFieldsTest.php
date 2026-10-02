<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAcademicFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_lets_scholars_edit_course_year_level_and_staff_schools(): void
    {
        [$scholar, $club, $school] = $this->makeApprovedScholarWithSchool();
        $secondSchool = ScholarshipClubSchool::createForClub('Siargao National Science High School', $club->id);

        $this->actingAs($scholar)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('name="course_year_level"', false)
            ->assertSee('name="year_level"', false)
            ->assertSee('name="scholarship_club_school_id"', false)
            ->assertSee('Dapa National High School')
            ->assertSee('Siargao National Science High School')
            ->assertSee('1st Year')
            ->assertSee('2nd Year')
            ->assertSee('3rd Year')
            ->assertSee('4th Year')
            ->assertDontSee('<select class="form-select" id="course_year_level"', false)
            ->assertDontSee('BSICT');

        $this->actingAs($scholar)
            ->put(route('user.profile.update'), [
                'full_name' => $scholar->full_name,
                'course_year_level' => 'Bachelor of Science in Computer Engineering',
                'year_level' => '3rd Year',
                'scholarship_club_school_id' => $secondSchool->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $scholar->refresh();
        $this->assertSame('Bachelor of Science in Computer Engineering', $scholar->course_year_level);
        $this->assertSame('3rd Year', $scholar->year_level);
        $this->assertSame($secondSchool->id, $scholar->scholarship_club_school_id);
        $this->assertSame('Siargao National Science High School', $scholar->school_university);

        $this->actingAs($scholar)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('Bachelor of Science in Computer Engineering')
            ->assertSee('value="3rd Year"', false)
            ->assertSee('value="'.$secondSchool->id.'"', false);
    }

    public function test_profile_rejects_course_initials_invalid_year_and_other_club_schools(): void
    {
        [$scholar, $club, $school] = $this->makeApprovedScholarWithSchool();

        $otherProgram = ScholarshipProgram::create([
            'location_name' => 'Tandag',
            'location_type' => 'city_municipality',
            'name' => 'Tandag, Surigao del Sur',
            'display_name' => 'Tandag, Surigao del Sur',
            'province_name' => 'Surigao del Sur',
            'slug' => 'tandag-profile-academic',
            'is_active' => true,
        ]);
        $otherClub = ScholarshipClub::create([
            'name' => 'Tandag Scholars Club',
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
            'scholarship_program_id' => $otherProgram->id,
            'is_active' => true,
        ]);
        $otherSchool = ScholarshipClubSchool::createForClub('Tandag National High School', $otherClub->id);

        $this->actingAs($scholar)
            ->from(route('user.profile'))
            ->put(route('user.profile.update'), [
                'full_name' => $scholar->full_name,
                'course_year_level' => 'BSICT',
                'year_level' => '5th Year',
                'scholarship_club_school_id' => $otherSchool->id,
            ])
            ->assertRedirect(route('user.profile'))
            ->assertSessionHasErrors(['course_year_level', 'year_level', 'scholarship_club_school_id']);

        $scholar->refresh();
        $this->assertSame('Bachelor of Science in Information Technology', $scholar->course_year_level);
        $this->assertSame('2nd Year', $scholar->year_level);
        $this->assertSame($school->id, $scholar->scholarship_club_school_id);
        $this->assertSame('Dapa National High School', $scholar->school_university);
        $this->assertSame($club->id, $scholar->scholarship_club_id);
    }

    public function test_staff_school_changes_appear_on_profile_and_registration(): void
    {
        [$scholar, $club] = $this->makeApprovedScholarWithSchool();

        $staff = User::register([
            'full_name' => 'Academic Staff',
            'scholar_id' => 'STAFF-ACADEMIC-001',
            'email' => 'academic-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $scholar->scholarship_program_id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($staff)
            ->post(route('staff.settings.schools.store'), [
                'name' => 'Surigao State College of Technology',
            ])
            ->assertRedirect(route('staff.settings'));

        $added = ScholarshipClubSchool::query()
            ->where('scholarship_club_id', $club->id)
            ->where('name', 'Surigao State College of Technology')
            ->first();
        $this->assertNotNull($added);

        $this->actingAs($scholar)
            ->get(route('user.profile'))
            ->assertOk()
            ->assertSee('Surigao State College of Technology');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Surigao State College of Technology');
    }

    /**
     * @return array{0: User, 1: ScholarshipClub, 2: ScholarshipClubSchool}
     */
    private function makeApprovedScholarWithSchool(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-profile-academic',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::create([
            'name' => 'Dapa Scholars Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);

        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);

        $scholar = User::register([
            'full_name' => 'Academic Scholar',
            'scholar_id' => 'SNS-ACADEMIC-001',
            'email' => 'academic-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'school_university' => $school->name,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '2nd Year',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        return [$scholar, $club, $school];
    }
}
