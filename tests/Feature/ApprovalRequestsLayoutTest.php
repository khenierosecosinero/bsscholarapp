<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalRequestsLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_requests_table_shows_aligned_pending_badge_and_labeled_actions(): void
    {
        [$staff] = $this->makeStaffWithPendingScholar();

        $html = $this->actingAs($staff)
            ->get(route('staff.approval-requests'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('staff-approval-table', $html);
        $this->assertStringContainsString('Scholar Information', $html);
        $this->assertStringContainsString('Account Details', $html);
        $this->assertStringContainsString('Municipality / City', $html);
        $this->assertStringContainsString('Province', $html);
        $this->assertStringContainsString('Date Registered', $html);
        $this->assertStringContainsString('>Pending<', $html);
        $this->assertStringContainsString('staff-approval-status', $html);
        $this->assertStringContainsString('staff-approval-actions', $html);
        $this->assertStringContainsString('>VIEW</a>', $html);
        $this->assertStringContainsString('>APPROVE</button>', $html);
        $this->assertStringContainsString('>REJECT</button>', $html);
        $this->assertLessThan(strpos($html, '>APPROVE</button>'), strpos($html, '>VIEW</a>'));
        $this->assertLessThan(strpos($html, '>REJECT</button>'), strpos($html, '>APPROVE</button>'));
        $this->assertStringContainsString('data-ajax-approval="approve"', $html);
        $this->assertStringContainsString('data-ajax-approval="reject"', $html);
        $this->assertStringContainsString(route('staff.scholars.show', User::query()->where('email', 'pending-approval@example.com')->first()), $html);
        $this->assertStringNotContainsString('👁', $html);
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
            'slug' => 'surigao-city-approval-layout',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Keneli Scholarship', $program->id);

        $staff = User::register([
            'full_name' => 'Approval Staff',
            'scholar_id' => 'STAFF-APPROVAL-001',
            'email' => 'approval-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar = User::register([
            'full_name' => 'Pending Scholar',
            'scholar_id' => 'SCH-APPROVAL-001',
            'email' => 'pending-approval@example.com',
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
