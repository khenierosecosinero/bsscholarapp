<?php

namespace Tests\Feature;

use App\Models\AcademicSetting;
use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccountSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_replaces_announcements_with_the_active_account_summary(): void
    {
        $scholar = $this->makeScholar(User::STATUS_APPROVED, '2026-10-15 09:00:00');
        $admin = User::register([
            'full_name' => 'Summary Admin',
            'scholar_id' => 'ADMIN-SUMMARY-001',
            'email' => 'summary-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
            'is_admin' => true,
        ]);

        $service = app(AcademicSettingsService::class);
        $older = AcademicSetting::create([
            'year_start' => 2025,
            'year_end' => 2026,
            'semester' => '2nd Semester',
            'is_active' => false,
        ]);
        $active = AcademicSetting::create([
            'year_start' => 2026,
            'year_end' => 2027,
            'semester' => '1st Semester',
            'is_active' => true,
        ]);
        $service->clearCache();

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Account Summary')
            ->assertSee('Account Status')
            ->assertSee('Approved')
            ->assertSee('Member Since')
            ->assertSee('Oct 2026')
            ->assertSee('Academic Year')
            ->assertSee('2026–2027')
            ->assertSee('Current Semester')
            ->assertSee('1st Semester')
            ->assertSee('UPCOMING EVENTS')
            ->assertSee('RECENT ACTIVITIES')
            ->assertSeeInOrder([
                'DOCUMENTS',
                'CALENDAR OF ACTIVITIES',
                'RECENT ACTIVITIES',
                'Account Summary',
            ])
            ->assertDontSee('>ANNOUNCEMENTS<', false)
            ->assertDontSee('No announcements.');

        $service->activate($older->fresh(), $admin);
        $older->update(['semester' => '2nd Semester']);
        $service->clearCache();

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Academic Year',
                '2025–2026',
                'Current Semester',
                '2nd Semester',
            ]);

        $this->assertTrue($older->fresh()->is_active);
        $this->assertFalse($active->fresh()->is_active);
    }

    public function test_pending_scholar_sees_pending_status_on_the_account_summary(): void
    {
        $scholar = $this->makeScholar(User::STATUS_PENDING, '2026-10-02 08:00:00');

        $this->actingAs($scholar)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Account Summary')
            ->assertSee('account-summary-badge-pending', false)
            ->assertSee('Pending')
            ->assertDontSee('account-summary-badge-approved', false);
    }

    private function makeScholar(string $status, string $createdAt): User
    {
        $city = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'sc-account-summary-'.$status,
            'is_active' => true,
        ]);
        $club = ScholarshipClub::createForProgram('Summary Club '.$status, $city->id);

        $scholar = User::register([
            'full_name' => 'Summary Scholar',
            'scholar_id' => 'SCH-SUMMARY-'.strtoupper($status),
            'email' => 'summary-'.$status.'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => $status,
            'scholarship_program_id' => $city->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ]);

        $scholar->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $scholar->fresh();
    }
}
