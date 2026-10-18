<?php

namespace Tests\Feature;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipClubSchool;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_email_verification_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('verification.notice'));
        $this->assertFalse(Route::has('verification.verify'));
        $this->assertFalse(Route::has('verification.send'));

        $this->get('/email/verify')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }

    public function test_unverified_scholar_can_log_in_and_reach_dashboard(): void
    {
        $user = $this->makeScholar(['email_verified_at' => null, 'status' => User::STATUS_APPROVED]);

        $this->assertNull($user->email_verified_at);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('Verify Your Email')
            ->assertDontSee('Resend verification email')
            ->assertDontSee('We sent a verification link to');
    }

    public function test_scholar_registration_does_not_send_verification_email(): void
    {
        Notification::fake();

        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao City',
            'location_type' => 'city_municipality',
            'name' => 'Surigao City Scholarship Program',
            'display_name' => 'Surigao City',
            'province_name' => 'Surigao del Norte',
            'slug' => 'surigao-city-no-verify-'.uniqid(),
            'is_active' => true,
        ]);

        $club = ScholarshipClub::createForProgram('No Verify Club', $program->id);
        $school = ScholarshipClubSchool::createForClub('Sample University', $club->id);

        $email = 'new-scholar-'.uniqid().'@example.com';

        $this->post(route('register.post'), [
            'full_name' => 'New Scholar',
            'scholar_id' => 'SCH-NV-'.uniqid(),
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'scholarship_club_id' => $club->id,
            'scholarship_club_school_id' => $school->id,
            'year_level' => '1st Year',
            'course_year_level' => 'Bachelor of Science in Information Technology',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');

        $this->assertDatabaseHas('users', ['email' => $email]);

        $user = User::where('email', $email)->firstOrFail();

        Notification::assertNothingSent();
        Notification::assertNotSentTo($user, VerifyEmail::class);

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertStringNotContainsString('verification', strtolower((string) session('success')));
        $this->assertStringNotContainsString('verification', strtolower((string) session('warning')));

        $this->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('Verify Your Email');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeScholar(array $overrides = []): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-ev-'.uniqid(),
            'is_active' => true,
        ]);

        $user = User::register([
            'full_name' => 'Dashboard Scholar',
            'scholar_id' => 'SCH-EV-'.uniqid(),
            'email' => 'scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);

        if (array_key_exists('email_verified_at', $overrides) || array_key_exists('status', $overrides)) {
            $user->forceFill(collect($overrides)->only(['email_verified_at', 'status'])->all())->save();
        }

        return $user->fresh();
    }
}
