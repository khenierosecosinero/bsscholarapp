<?php

namespace Tests\Feature;

use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSecurityShortcutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_shortcuts_open_their_profile_sections(): void
    {
        $user = $this->makeApprovedScholar();

        $profile = $this->actingAs($user)->get(route('user.profile'));
        $profile->assertOk();
        $profile->assertSee('href="'.route('user.profile', ['tab' => 'academic-settings']).'#academic-settings"', false);
        $profile->assertSee('href="'.route('user.profile', ['tab' => 'security']).'#security"', false);
        $profile->assertSee('href="'.route('user.profile', ['tab' => 'account-settings']).'#account-settings"', false);

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'academic-settings']))
            ->assertOk()
            ->assertSee('YOUR ACADEMIC PERIOD')
            ->assertDontSee('hidden id="academic-settings"', false);

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('CHANGE PASSWORD')
            ->assertSee('name="current_password"', false);

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'account-settings']))
            ->assertOk()
            ->assertSee('LOGIN CREDENTIALS')
            ->assertSee($user->email);
    }

    private function makeApprovedScholar(): User
    {
        $province = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-shortcuts',
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Shortcut Scholar',
            'scholar_id' => 'SNS-SHORTCUT-001',
            'email' => 'shortcut-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $province->id,
            'province' => 'Surigao del Norte',
        ]);
    }
}
