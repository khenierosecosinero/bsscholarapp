<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffApprovedScholarsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholars_list_shows_only_approved_accounts(): void
    {
        [$staff, $approved, $pending, $rejected] = $this->makeStaffWithScholars();

        $this->actingAs($staff)
            ->get(route('staff.scholars'))
            ->assertOk()
            ->assertSee($approved->full_name)
            ->assertSee($approved->scholar_id)
            ->assertDontSee($pending->full_name)
            ->assertDontSee($pending->email)
            ->assertDontSee($rejected->full_name)
            ->assertDontSee($rejected->email);

        $this->actingAs($staff)
            ->get(route('staff.scholars', ['search' => 'Pending']))
            ->assertOk()
            ->assertDontSee($pending->full_name)
            ->assertSee('No scholars found for this Scholarship Club.');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function makeStaffWithScholars(): array
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-approved-scholars',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('KENI SCHOLARSHIP', $program->id);

        $staff = User::register([
            'full_name' => 'List Staff',
            'scholar_id' => 'STAFF-LIST-001',
            'email' => 'list-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $approved = User::register([
            'full_name' => 'Approved List Scholar',
            'scholar_id' => 'SCH-LIST-OK',
            'email' => 'approved-list@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $pending = User::register([
            'full_name' => 'Pending List Scholar',
            'scholar_id' => 'SCH-LIST-PENDING',
            'email' => 'pending-list@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $rejected = User::register([
            'full_name' => 'Rejected List Scholar',
            'scholar_id' => 'SCH-LIST-REJECTED',
            'email' => 'rejected-list@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_REJECTED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        return [$staff, $approved, $pending, $rejected];
    }
}
