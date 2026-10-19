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

    public function test_rejecting_scholar_keeps_application_and_redirects_to_scholars_list(): void
    {
        [$staff, $scholar] = $this->makeStaffWithPendingScholar();

        $this->actingAs($staff)
            ->from(route('staff.scholars.show', $scholar))
            ->post(route('staff.scholars.reject', $scholar))
            ->assertRedirect(route('staff.scholars'))
            ->assertSessionHas('success');

        $scholar->refresh();
        $this->assertSame(User::STATUS_REJECTED, $scholar->status);
        $this->assertFalse($scholar->canLogin());

        $this->actingAs($staff)
            ->get(route('staff.scholars.show', $scholar))
            ->assertOk()
            ->assertSee('Rejected');

        $this->actingAs($staff)
            ->get(route('staff.approval-requests', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee($scholar->full_name)
            ->assertSee('Rejected');

        $this->actingAs($staff)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertDontSee($scholar->email);
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
            ->assertSessionHas('success', 'Scholar account deleted successfully.');

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
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-nav-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Nav Club', $program->id);

        $staff = User::register([
            'full_name' => 'Nav Staff',
            'scholar_id' => 'STAFF-NAV-'.uniqid(),
            'email' => 'nav-staff-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Pending Nav Scholar',
            'scholar_id' => 'SCH-NAV-'.uniqid(),
            'email' => 'pending-nav-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $scholar];
    }
}
