<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\AcademicSettingsService;
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
            ->assertSee('AY 2026–2027')
            ->assertSee('2nd Semester')
            ->assertSee('27.00');
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
