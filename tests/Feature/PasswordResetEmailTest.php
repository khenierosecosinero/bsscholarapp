<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\MailConfigService;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_code_queues_password_reset_mailable(): void
    {
        Mail::fake();

        $this->mockReadyMail();

        $user = $this->makeScholar();

        app(PasswordResetService::class)->sendCode($user);

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && preg_match('/^\d{6}$/', $mail->code) === 1;
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => strtolower($user->email),
        ]);
    }

    public function test_send_code_is_blocked_when_smtp_is_not_ready(): void
    {
        Mail::fake();

        $this->mock(MailConfigService::class, function ($mail) {
            $mail->shouldReceive('syncRuntimeCredentials')->andReturnNull();
            $mail->shouldReceive('status')->andReturn([
                'ready' => false,
                'mailer' => 'smtp',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'scheme' => 'smtp',
                'username_configured' => false,
                'password_configured' => false,
                'from_configured' => false,
                'from_matches_username' => false,
                'username_preview' => '(empty)',
                'username_source' => 'missing',
                'issues' => ['MAIL_USERNAME is empty in this test double.'],
            ]);
        });

        $user = $this->makeScholar();

        try {
            app(PasswordResetService::class)->sendCode($user);
            $this->fail('Expected ValidationException when SMTP is not ready.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_forgot_password_form_sends_code_for_scholar(): void
    {
        Mail::fake();

        $this->mockReadyMail();

        $user = $this->makeScholar();

        $this->post(route('password.find'), [
            'email' => $user->email,
        ])->assertRedirect(route('password.request'));

        $this->post(route('password.email'), [
            'recovery_email' => $user->email,
        ])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHas('success');

        Mail::assertSent(PasswordResetCodeMail::class);
    }

    private function mockReadyMail(): void
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

    private function makeScholar(): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-mail-'.uniqid(),
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Mail Test Scholar',
            'scholar_id' => 'SCH-MAIL-'.uniqid(),
            'email' => 'mail-scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }
}
