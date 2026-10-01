<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarLayoutConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_and_admin_sidebar_css_matches_scholar_dimensions(): void
    {
        $scholarCss = file_get_contents(resource_path('css/styles.css'));
        $staffCss = file_get_contents(public_path('css/staff-admin.css'));
        $appJs = file_get_contents(resource_path('js/user-app.js'));

        $this->assertStringContainsString('.sidebar{width:300px', $scholarCss);
        $this->assertStringContainsString('padding:28px 20px', $scholarCss);
        $this->assertStringContainsString('@media (max-width:1000px){', $scholarCss);

        $this->assertMatchesRegularExpression('/\.staff-sidebar\s*\{[^}]*width:\s*300px/s', $staffCss);
        $this->assertMatchesRegularExpression('/\.staff-sidebar\s*\{[^}]*padding:\s*28px 20px/s', $staffCss);
        $this->assertMatchesRegularExpression('/\.staff-sidebar\s*\{[^}]*gap:\s*20px/s', $staffCss);
        $this->assertMatchesRegularExpression('/\.staff-brand-logo\s*\{[^}]*width:\s*52px/s', $staffCss);
        $this->assertStringContainsString('@media (max-width: 1000px)', $staffCss);
        $this->assertStringContainsString('max-width: 86vw', $staffCss);
        $this->assertStringNotContainsString('width: 260px', $staffCss);

        $this->assertStringContainsString("matchMedia('(max-width: 1000px)')", $appJs);

        $this->assertStringContainsString('.app-logout-btn', $scholarCss);
        $this->assertStringContainsString('background:#dc2626', $scholarCss);
        $this->assertStringContainsString('.app-logout-btn:hover', $scholarCss);
        $this->assertStringContainsString('background: #dc2626', $staffCss);
        $this->assertStringContainsString('.staff-logout-btn:hover', $staffCss);
    }

    public function test_scholar_sidebar_keeps_logo_profile_nav_and_logout_order(): void
    {
        $html = $this->actingAs($this->makeApprovedScholar())
            ->get(route('user.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="sidebar"', $html);
        $this->assertStringContainsString('class="sidebar-user-chip"', $html);
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('Events', $html);
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('app-logout-icon', $html);
        $this->assertStringContainsString('logout-btn', $html);

        $this->assertLessThan(strpos($html, 'sidebar-user-chip'), strpos($html, 'class="logo"'));
        $this->assertLessThan(strpos($html, 'aria-label="Main navigation"'), strpos($html, 'sidebar-user-chip'));
        $this->assertLessThan(strpos($html, 'logout-btn'), strpos($html, 'aria-label="Main navigation"'));
    }

    public function test_staff_sidebar_matches_scholar_structure_and_keeps_nav_items(): void
    {
        $html = $this->actingAs($this->makeApprovedStaff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="staff-sidebar"', $html);
        $this->assertStringContainsString('class="staff-user-chip"', $html);
        $this->assertStringContainsString('DASHBOARD', $html);
        $this->assertStringContainsString('MANAGEMENT', $html);
        $this->assertStringContainsString('REPORTS', $html);
        $this->assertStringContainsString('SYSTEM', $html);
        $this->assertStringContainsString('Scholars', $html);
        $this->assertStringContainsString('Approval Requests', $html);
        $this->assertStringContainsString('Service Hours Reports', $html);
        $this->assertStringContainsString('staff-logout-btn', $html);
        $this->assertStringContainsString('app-logout-icon', $html);

        $this->assertLessThan(strpos($html, 'staff-user-chip'), strpos($html, 'staff-brand-logo'));
        $this->assertLessThan(strpos($html, 'aria-label="Staff navigation"'), strpos($html, 'staff-user-chip'));
        $this->assertLessThan(strpos($html, 'staff-logout-btn'), strpos($html, 'aria-label="Staff navigation"'));
        $this->assertStringContainsString('Scholar Staff', $html);
        $this->assertStringNotContainsString('STAFF-SIDEBAR-001', $html);
    }

    public function test_admin_sidebar_matches_scholar_structure_and_keeps_nav_items(): void
    {
        $html = $this->actingAs($this->makeAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="staff-sidebar"', $html);
        $this->assertStringContainsString('class="staff-user-chip"', $html);
        $this->assertStringContainsString('ADMINISTRATION', $html);
        $this->assertStringContainsString('Scholar Staff', $html);
        $this->assertStringContainsString('Admin Settings', $html);
        $this->assertStringContainsString('System Administrator', $html);
        $this->assertStringContainsString('staff-logout-btn', $html);
        $this->assertStringContainsString('app-logout-icon', $html);
        $this->assertStringNotContainsString('>Locations</span>', $html);

        $this->assertLessThan(strpos($html, 'staff-user-chip'), strpos($html, 'staff-brand-logo'));
        $this->assertLessThan(strpos($html, 'aria-label="Admin navigation"'), strpos($html, 'staff-user-chip'));
        $this->assertLessThan(strpos($html, 'staff-logout-btn'), strpos($html, 'aria-label="Admin navigation"'));
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'System Admin',
            'scholar_id' => 'ADMIN-SIDEBAR-001',
            'email' => 'admin-sidebar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    private function makeApprovedStaff(): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-sidebar-layout',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Dapa Scholars Club', $program->id);

        return User::register([
            'full_name' => 'Sidebar Staff',
            'scholar_id' => 'STAFF-SIDEBAR-001',
            'email' => 'staff-sidebar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeApprovedScholar(): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'sdn-sidebar-layout',
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Sidebar Scholar',
            'scholar_id' => 'SCH-SIDEBAR-001',
            'email' => 'scholar-sidebar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }
}
