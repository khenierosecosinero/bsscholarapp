<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipClubSchoolTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-school-mgmt',
            'is_active' => true,
        ]);
    }

    private function makeStaff(ScholarshipProgram $program, ScholarshipClub $club): User
    {
        return User::register([
            'full_name' => 'School Staff',
            'scholar_id' => 'STAFF-SCHOOL-001',
            'email' => 'school-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
    }

    public function test_staff_can_add_edit_and_remove_club_schools(): void
    {
        $program = $this->makeProgram();
        $club = ScholarshipClub::create([
            'name' => 'Dapa Scholars Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);
        $staff = $this->makeStaff($program, $club);

        $this->actingAs($staff)
            ->get(route('staff.settings'))
            ->assertOk()
            ->assertSee('School/University Management')
            ->assertSee(route('staff.settings.schools.create', [], false));

        $this->actingAs($staff)
            ->post(route('staff.settings.schools.store'), [
                'name' => 'Dapa National High School',
            ])
            ->assertRedirect(route('staff.settings'));

        $school = ScholarshipClubSchool::query()->first();
        $this->assertNotNull($school);
        $this->assertSame($club->id, $school->scholarship_club_id);
        $this->assertSame('Dapa National High School', $school->name);
        $this->assertSame($staff->id, $school->created_by);

        $this->actingAs($staff)
            ->put(route('staff.settings.schools.update', $school), [
                'name' => 'Siargao National Science High School',
            ])
            ->assertRedirect(route('staff.settings'));

        $this->assertSame('Siargao National Science High School', $school->fresh()->name);

        $this->actingAs($staff)
            ->delete(route('staff.settings.schools.destroy', $school))
            ->assertRedirect(route('staff.settings'));

        $this->assertDatabaseMissing('scholarship_club_schools', ['id' => $school->id]);
    }

    public function test_scholar_must_select_a_school_from_the_chosen_club(): void
    {
        $program = $this->makeProgram();
        $otherProgram = ScholarshipProgram::create([
            'location_name' => 'Tandag',
            'location_type' => 'city_municipality',
            'name' => 'Tandag, Surigao del Sur',
            'display_name' => 'Tandag, Surigao del Sur',
            'province_name' => 'Surigao del Sur',
            'slug' => 'tandag-school-mgmt',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::create([
            'name' => 'Dapa Scholars Club',
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
            'scholarship_program_id' => $program->id,
            'is_active' => true,
        ]);
        $otherClub = ScholarshipClub::create([
            'name' => 'Tandag Scholars Club',
            'city' => 'Tandag',
            'province' => 'Surigao del Sur',
            'scholarship_program_id' => $otherProgram->id,
            'is_active' => true,
        ]);
        $school = ScholarshipClubSchool::createForClub('Dapa National High School', $club->id);
        $otherSchool = ScholarshipClubSchool::createForClub('Tandag National High School', $otherClub->id);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="scholarship_club_school_id"', false)
            ->assertSee('Dapa National High School');

        $this->post(route('register.post'), [
            'full_name' => 'School Scholar',
            'scholar_id' => 'SNS-SCHOOL-001',
            'scholarship_club_id' => $club->id,
            'email' => 'school-scholar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_school_id');

        $this->post(route('register.post'), [
            'full_name' => 'School Scholar',
            'scholar_id' => 'SNS-SCHOOL-001',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $otherSchool->id,
            'email' => 'school-scholar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('scholarship_club_school_id');

        $this->post(route('register.post'), [
            'full_name' => 'School Scholar',
            'scholar_id' => 'SNS-SCHOOL-001',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'email' => 'school-scholar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $scholar = User::query()->where('email', 'school-scholar@example.com')->first();
        $this->assertSame($club->id, $scholar->scholarship_club_id);
        $this->assertSame($school->id, $scholar->scholarship_club_school_id);
        $this->assertSame('Dapa National High School', $scholar->school_university);
    }
}
