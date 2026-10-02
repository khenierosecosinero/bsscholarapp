<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEditModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_profile_uses_edit_save_workflow_and_keeps_guardian_separate(): void
    {
        $html = $this->actingAs($this->makeScholar())
            ->get(route('user.profile'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, 'data-profile-edit-form'));
        $this->assertStringContainsString('data-profile-edit', $html);
        $this->assertStringContainsString('data-profile-cancel', $html);
        $this->assertStringContainsString('data-profile-save', $html);
        $this->assertStringContainsString('>Edit</button>', $html);
        $this->assertStringContainsString('Save Changes', $html);
        $this->assertStringContainsString('Save Guardian Info', $html);
        $this->assertStringContainsString('name="course_year_level"', $html);
        $this->assertStringContainsString('name="year_level"', $html);
        $this->assertStringContainsString('name="scholarship_club_school_id"', $html);
        $this->assertGreaterThan(
            strpos($html, 'scholar-profile-form'),
            strpos($html, 'Save Guardian Info')
        );
    }

    public function test_staff_and_admin_settings_use_the_same_edit_save_workflow(): void
    {
        $staffHtml = $this->actingAs($this->makeStaff())
            ->get(route('staff.settings'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-profile-edit-form', $staffHtml);
        $this->assertStringContainsString('>Edit</button>', $staffHtml);
        $this->assertStringContainsString('Save Changes', $staffHtml);
        $this->assertStringContainsString('name="scholarship_club_name"', $staffHtml);
        $this->assertStringContainsString('name="cellphone_number"', $staffHtml);

        $adminHtml = $this->actingAs($this->makeAdmin())
            ->get(route('admin.settings'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-profile-edit-form', $adminHtml);
        $this->assertStringContainsString('>Edit</button>', $adminHtml);
        $this->assertStringContainsString('Save Changes', $adminHtml);
        $this->assertStringContainsString('name="full_name"', $adminHtml);
        $this->assertStringContainsString('name="email"', $adminHtml);
        $this->assertStringNotContainsString('Save Account Info', $adminHtml);
    }

    private function makeScholar(): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'sdn-profile-edit',
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Edit Mode Scholar',
            'scholar_id' => 'SCH-EDIT-001',
            'email' => 'edit-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeStaff(): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Dapa',
            'location_type' => 'city_municipality',
            'name' => 'Dapa, Surigao del Norte',
            'display_name' => 'Dapa, Surigao del Norte',
            'province_name' => 'Surigao del Norte',
            'slug' => 'dapa-profile-edit',
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('Dapa Edit Club', $program->id);

        return User::register([
            'full_name' => 'Edit Mode Staff',
            'scholar_id' => 'STAFF-EDIT-001',
            'email' => 'edit-staff@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR_STAFF,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'scholarship_club_id' => $club->id,
            'city' => 'Dapa',
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'Edit Mode Admin',
            'scholar_id' => 'ADMIN-EDIT-001',
            'email' => 'edit-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);

        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }
}
