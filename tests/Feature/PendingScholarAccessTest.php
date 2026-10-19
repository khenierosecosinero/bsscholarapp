<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingScholarAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_scholar_can_log_in_to_restricted_dashboard_only(): void
    {
        $scholar = $this->makePendingScholar();

        $this->post(route('login.post'), [
            'email' => $scholar->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($scholar);

        $dashboard = $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('ACCOUNT STATUS')
            ->assertDontSee('Your account is pending approval. Some features will remain unavailable until your account is approved.')
            ->assertDontSee('href="'.route('user.profile').'"', false);

        $dashboard->assertSee('Profile & Settings');
        $dashboard->assertSee('is-disabled', false);

        $this->get(route('user.profile'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas(
                'warning',
                'Your account is pending approval. Profile and Settings access will be available after your account is approved.'
            );

        $this->put(route('user.profile.update'), [
            'full_name' => 'Should Not Update',
            'cellphone_number' => '09171234567',
        ])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas(
                'warning',
                'Your account is pending approval. Profile and Settings access will be available after your account is approved.'
            );

        $this->get(route('user.events'))
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('warning');

        $this->get(route('user.documents'))
            ->assertRedirect(route('user.dashboard'));

        $this->get(route('user.service-hours'))
            ->assertRedirect(route('user.dashboard'));

        // Staff/admin portals reject scholars (403) rather than opening those dashboards.
        $this->get(route('staff.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_approved_scholar_regains_full_access_without_reregistering(): void
    {
        $scholar = $this->makePendingScholar();

        $this->post(route('login.post'), [
            'email' => $scholar->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));

        $scholar->forceFill(['status' => User::STATUS_APPROVED])->save();
        $scholar->activateFreshEventList();
        $this->actingAs($scholar->fresh());

        $this->get(route('user.events'))->assertOk();
        $this->get(route('user.documents'))->assertOk();
        $this->get(route('user.service-hours'))->assertOk();
        $this->get(route('user.profile'))->assertOk();
    }

    public function test_rejected_scholar_cannot_log_in(): void
    {
        $scholar = $this->makePendingScholar();
        $scholar->forceFill(['status' => User::STATUS_REJECTED])->save();

        $this->post(route('login.post'), [
            'email' => $scholar->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('rejected', strtolower((string) session('errors')->first('email')));
    }

    public function test_pending_staff_can_only_open_pending_approval_page(): void
    {
        $city = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-pending-staff-access',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Pending Staff Access Club', $city->id);

        $staff = User::register([
            'full_name' => 'Pending Staff Access',
            'scholar_id' => 'STAFF-PEND-ACCESS',
            'email' => 'pending-staff-access@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);

        $this->post(route('login.post'), [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertRedirect(route('staff.pending-approval'));

        $this->assertAuthenticatedAs($staff);

        $this->get(route('staff.pending-approval'))
            ->assertOk()
            ->assertSee('Pending Approval');

        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.pending-approval'));

        $this->get(route('staff.scholars'))
            ->assertRedirect(route('staff.pending-approval'));
    }

    private function makePendingScholar(): User
    {
        $city = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-pending-scholar-access',
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Pending Scholar Access Club', $city->id);
        ScholarshipClubSchool::createForClub('Pending Access School', $club->id);

        return User::register([
            'full_name' => 'Pending Scholar Access',
            'scholar_id' => 'SCH-PEND-ACCESS',
            'email' => 'pending-scholar-access@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
    }
}
