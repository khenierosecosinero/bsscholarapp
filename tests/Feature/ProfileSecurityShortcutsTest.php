<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('CURRENT ACADEMIC PERIOD')
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

    public function test_profile_academic_period_apply_view_updates_summaries_without_header_filters(): void
    {
        $user = $this->makeApprovedScholar();
        $academic = app(AcademicSettingsService::class);
        $academic->updateGlobal(['year_start' => 2025, 'semester' => '1st Semester'], $user);
        $academic->updateGlobal(['year_start' => 2026, 'semester' => '1st Semester'], $user);

        $event = Event::create([
            'title' => 'Profile Period Event',
            'description' => 'Period filter check',
            'location' => 'Surigao City',
            'starts_at' => now()->setDate(2026, 2, 15)->setTime(8, 0),
            'ends_at' => now()->setDate(2026, 2, 15)->setTime(12, 0),
            'service_hours' => 27,
            'organizer' => 'BSSA',
            'status' => 'completed',
            'scholarship_program_id' => $user->scholarship_program_id,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'academic_year_start' => 2026,
            'academic_year_end' => 2027,
            'semester' => '2nd Semester',
            'check_in' => $event->starts_at->copy()->addMinutes(5),
            'check_out' => $event->ends_at,
            'hours_earned' => 27,
            'status' => Attendance::STATUS_APPROVED,
        ]);

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'academic-settings']))
            ->assertOk()
            ->assertSee('CURRENT ACADEMIC PERIOD')
            ->assertSee('Apply View')
            ->assertDontSee('aria-label="Academic year"', false);

        $this->actingAs($user)
            ->from(route('user.profile', ['tab' => 'academic-settings']))
            ->put(route('academic.period.update'), [
                'year_start' => 2026,
                'semester' => '2nd Semester',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'academic-settings']))
            ->assertSessionHas('success');

        $this->assertSame(2026, $academic->selectedPeriod()->year_start);
        $this->assertSame('2nd Semester', $academic->selectedPeriod()->semester);
        $this->assertSame(2026, Attendance::query()->where('user_id', $user->id)->value('academic_year_start'));

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'academic-settings']))
            ->assertOk()
            ->assertSee('value="2026" selected', false)
            ->assertSee('value="2nd Semester" selected', false)
            ->assertSee('2026–2027')
            ->assertSee('2nd Semester')
            ->assertSee('27')
            ->assertDontSee('aria-label="Academic year"', false);

        $this->actingAs($user)
            ->get(route('user.service-hours'))
            ->assertOk()
            ->assertSee('2nd Semester')
            ->assertSee('27.00')
            ->assertDontSee('class="academic-period-filter"', false)
            ->assertDontSee('sh-sidebar-filters', false)
            ->assertDontSee('current-semester-card', false);
    }

    public function test_saved_academic_view_survives_logout_without_changing_admin_or_attendance_records(): void
    {
        $user = $this->makeApprovedScholar();
        $academic = app(AcademicSettingsService::class);
        $academic->updateGlobal(['year_start' => 2025, 'semester' => '1st Semester'], $user);
        $academic->updateGlobal(['year_start' => 2026, 'semester' => '1st Semester'], $user);

        $this->actingAs($user)
            ->from(route('user.profile', ['tab' => 'academic-settings']))
            ->put(route('user.profile.academic'), [
                'program_start_year' => 2025,
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'academic-settings']))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->from(route('user.profile', ['tab' => 'academic-settings']))
            ->put(route('academic.period.update'), [
                'year_start' => 2025,
                'semester' => '2nd Semester',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'academic-settings']));

        $this->actingAs($user)
            ->from(route('user.profile', ['tab' => 'academic-settings']))
            ->put(route('academic.period.update'), [
                'year_start' => 1999,
                'semester' => '1st Semester',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'academic-settings']))
            ->assertSessionHasErrors('year_start');

        $user->refresh();
        $this->assertSame(2025, (int) $user->program_start_year);
        $this->assertSame(2025, (int) $user->academic_year_start);
        $this->assertSame('2nd Semester', $user->semester);
        $this->assertSame(2026, $academic->current()->year_start);

        $this->actingAs($user)->put(route('user.profile.academic.global'), [
            'year_start' => 2025,
            'semester' => '2nd Semester',
        ])->assertForbidden();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('user.dashboard'));

        $this->get(route('user.profile', ['tab' => 'academic-settings']))
            ->assertOk()
            ->assertSee('class="tab-panel active" id="academic-settings"', false)
            ->assertSee('value="2025" selected', false)
            ->assertSee('value="2nd Semester" selected', false)
            ->assertSee('Surigao del Norte (Province)');

        $this->assertSame(2025, (int) $user->fresh()->program_start_year);
        $this->assertSame(2026, $academic->current()->year_start);
    }

    public function test_password_change_hashes_the_new_password_and_rejects_the_old_one(): void
    {
        $user = $this->makeApprovedScholar();

        $this->put(route('user.profile.password'), [
            'current_password' => 'password123',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect(route('login'));

        $this->actingAs($user)
            ->from(route('user.profile'))
            ->put(route('user.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'security']))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->from(route('user.profile'))
            ->put(route('user.profile.password'), [
                'current_password' => 'password123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'security']))
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->from(route('user.profile'))
            ->put(route('user.profile.password'), [
                'current_password' => 'password123',
                'password' => 'new-password-1',
                'password_confirmation' => 'different-1',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'security']))
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->put(route('user.profile.password'), [
                'current_password' => 'password123',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect(route('user.profile', ['tab' => 'security']))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);

        $this->get(route('user.profile', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('class="tab-panel active" id="security"', false)
            ->assertDontSee('value="new-password-1"', false)
            ->assertDontSee($user->password, false);

        $this->post(route('logout'));

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'new-password-1',
        ])->assertRedirect(route('user.dashboard'));
    }

    public function test_login_credentials_show_only_the_signed_in_account(): void
    {
        $user = $this->makeApprovedScholar();
        $other = User::register([
            'full_name' => 'Other Scholar',
            'scholar_id' => 'SNS-SHORTCUT-002',
            'email' => 'other-scholar@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $user->scholarship_program_id,
            'province' => 'Surigao del Norte',
        ]);

        $this->actingAs($user)
            ->get(route('user.profile', ['tab' => 'account-settings']))
            ->assertOk()
            ->assertSee('class="tab-panel active" id="account-settings"', false)
            ->assertSee($user->email)
            ->assertSee($user->scholar_id)
            ->assertSee('never shown')
            ->assertDontSee($other->email)
            ->assertDontSee($other->scholar_id)
            ->assertDontSee($user->password, false)
            ->assertSee('name="_token"', false);

        $user->forceFill([
            'scholar_id' => ' ',
        ])->save();

        $this->actingAs($user->fresh())
            ->get(route('user.profile', ['tab' => 'account-settings']))
            ->assertOk()
            ->assertSee('This account has no Scholar ID on file.')
            ->assertDontSee('SNS-SHORTCUT-001');
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
