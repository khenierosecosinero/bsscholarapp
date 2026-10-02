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

    public function test_profile_page_stacks_progress_and_calendar_under_profile_nav(): void
    {
        $html = $this->actingAs($this->makeApprovedScholar())
            ->get(route('user.profile'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sidebar-nav-widgets', $html);
        $this->assertStringContainsString('profile-my-progress-card', $html);
        $this->assertStringContainsString('dashboard-sidebar-calendar', $html);
        $this->assertStringContainsString('MY PROGRESS', $html);

        $this->assertMatchesRegularExpression(
            '/nav-label">Profile &amp; Settings<\/span>[\s\S]*?sidebar-nav-widgets[\s\S]*?profile-my-progress-card[\s\S]*?dashboard-sidebar-calendar[\s\S]*?class="sidebar-widgets"/',
            $html
        );

        $widgets = strpos($html, 'sidebar-nav-widgets');
        $progress = strpos($html, 'profile-my-progress-card');
        $calendar = strpos($html, 'dashboard-sidebar-calendar');
        $logout = strpos($html, 'logout-btn');

        $this->assertLessThan($progress, $widgets);
        $this->assertLessThan($calendar, $progress);
        $this->assertLessThan($logout, $calendar);
        $this->assertLessThan(strpos($html, 'class="sidebar-widgets"'), $calendar);
    }

    public function test_documents_page_stacks_overview_and_semester_under_profile_nav(): void
    {
        $html = $this->actingAs($this->makeApprovedScholar())
            ->get(route('user.documents'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sidebar-nav-widgets', $html);
        $this->assertStringContainsString('documents-overview-sidebar-card', $html);
        $this->assertStringContainsString('DOCUMENTS OVERVIEW', $html);
        $this->assertStringContainsString('current-semester-card', $html);

        $this->assertMatchesRegularExpression(
            '/nav-label">Profile &amp; Settings<\/span>[\s\S]*?sidebar-nav-widgets[\s\S]*?documents-overview-sidebar-card[\s\S]*?current-semester-card[\s\S]*?class="sidebar-widgets"/',
            $html
        );

        $widgets = strpos($html, 'sidebar-nav-widgets');
        $overview = strpos($html, 'documents-overview-sidebar-card');
        $semester = strpos($html, 'current-semester-card');
        $logout = strpos($html, 'logout-btn');

        $this->assertLessThan($overview, $widgets);
        $this->assertLessThan($semester, $overview);
        $this->assertLessThan($logout, $semester);
        $this->assertLessThan(strpos($html, 'class="sidebar-widgets"'), $semester);
    }

    public function test_calendar_page_stacks_upcoming_events_under_schedule_summary(): void
    {
        $html = $this->actingAs($this->makeApprovedScholar())
            ->get(route('user.calendar'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('schedule-summary-section', $html);
        $this->assertStringContainsString('MY SCHEDULE SUMMARY', $html);
        $this->assertStringContainsString('calendar-upcoming-card', $html);
        $this->assertStringContainsString('UPCOMING EVENTS', $html);
        $this->assertStringContainsString('No upcoming events.', $html);
        $this->assertStringContainsString('View All Events', $html);

        $this->assertMatchesRegularExpression(
            '/schedule-summary-section[\s\S]*?MY SCHEDULE SUMMARY[\s\S]*?calendar-upcoming-card[\s\S]*?UPCOMING EVENTS[\s\S]*?View All Events/',
            $html
        );

        $summary = strpos($html, 'schedule-summary-section');
        $upcoming = strpos($html, 'calendar-upcoming-card');
        $this->assertNotFalse($summary);
        $this->assertNotFalse($upcoming);
        $this->assertLessThan($upcoming, $summary);
        $this->assertStringNotContainsString('calendar-sidebar', $html);
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
