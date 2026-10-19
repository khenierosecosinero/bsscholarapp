<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_scholar_permanently_removes_account_and_allows_reregistration(): void
    {
        [$staff, $scholar] = $this->makeStaffWithPendingScholar();
        $email = $scholar->email;
        $scholarCode = $scholar->scholar_id;
        $clubId = (int) $scholar->scholarship_club_id;

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->post(route('staff.scholars.reject', $scholar))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('users', ['scholar_id' => $scholarCode]);

        $school = \App\Models\ScholarshipClubSchool::createForClub('Reject Reuse High School', $clubId);

        $this->post(route('register.post'), [
            'full_name' => 'Returning After Reject',
            'scholar_id' => $scholarCode,
            'scholarship_club_id' => $clubId,
            'scholarship_club_school_id' => $school->id,
            'course_year_level' => 'Bachelor of Science in Information Technology',
            'year_level' => '1st Year',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'scholar_id' => $scholarCode,
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
        ]);
    }

    public function test_deleting_scholar_redirects_to_scholars_list_without_404(): void
    {
        [$staff, $scholar] = $this->makeStaffWithPendingScholar();
        $scholar->forceFill(['status' => User::STATUS_APPROVED])->save();
        $scholarId = $scholar->id;

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->delete(route('staff.scholars.destroy', $scholar))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $scholarId]);

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $scholarId))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('warning');
    }

    public function test_approving_scholar_from_profile_lands_on_profile_with_success(): void
    {
        [$staff, $scholar] = $this->makeStaffWithPendingScholar();

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->post(route('staff.scholars.approve', $scholar))
            ->assertRedirect(route('staff.scholars.show', $scholar))
            ->assertSessionHas('success');

        $this->assertSame(User::STATUS_APPROVED, $scholar->fresh()->status);

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $scholar))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeStaffWithPendingScholar(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-nav-safety',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Nav Safety Club', $program->id);

        $staff = User::register([
            'full_name' => 'Nav Safety Staff',
            'scholar_id' => 'STAFF-NAV-001',
            'email' => 'nav-safety-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
        $club->forceFill(['created_by' => $staff->id])->save();

        $scholar = User::register([
            'full_name' => 'Nav Safety Scholar',
            'scholar_id' => 'SCH-NAV-001',
            'email' => 'nav-safety-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $scholar];
    }
}
