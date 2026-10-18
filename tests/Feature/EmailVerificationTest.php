<?php

namespace Tests\Feature;

use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_scholar_receives_verification_notification_when_smtp_ready(): void
    {
        Notification::fake();
        $this->fakeReadyMail();

        $user = $this->makeUnverifiedScholar();

        $this->assertTrue(app(MailConfigService::class)->isReady());
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_verification_link_marks_email_verified(): void
    {
        $user = $this->makeUnverifiedScholar();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_sends_once_then_skips_when_already_verified(): void
    {
        Notification::fake();
        $this->fakeReadyMail();

        $user = $this->makeUnverifiedScholar();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $user->forceFill(['email_verified_at' => now()])->save();
        Notification::fake();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('dashboard'));

        Notification::assertNothingSent();
    }

    public function test_array_mailer_is_not_reported_as_invalid_username(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers.smtp.username' => '',
            'mail.mailers.smtp.password' => '',
            'mail.from.address' => '',
        ]);

        $status = app(MailConfigService::class)->status();

        $this->assertTrue($status['ready']);
        $this->assertSame('array', $status['mailer']);
        $this->assertSame([], $status['issues']);
    }

    private function fakeReadyMail(): void
    {
        $this->mock(MailConfigService::class, function ($mail) {
            $mail->shouldReceive('syncRuntimeCredentials')->andReturnNull();
            $mail->shouldReceive('isReady')->andReturn(true);
            $mail->shouldReceive('status')->andReturn([
                'ready' => true,
                'mailer' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'scheme' => 'smtp',
                'username_configured' => true,
                'password_configured' => true,
                'from_configured' => true,
                'from_matches_username' => true,
                'username_preview' => 'b***@gmail.com',
                'username_source' => 'config',
                'issues' => [],
            ]);
        });
    }

    private function makeUnverifiedScholar(): User
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
            'full_name' => 'Unverified Scholar',
            'scholar_id' => 'SCH-EV-'.uniqid(),
            'email' => 'unverified-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);

        $user->forceFill(['email_verified_at' => null])->save();

        return $user->fresh();
    }
}
