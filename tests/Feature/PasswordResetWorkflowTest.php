<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\MailConfigService;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordResetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_account_and_complete_reset_with_primary_email(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('workflow-scholar@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success')
            ->assertSessionHas(PasswordResetService::SESSION_ACCOUNT_ID, $user->id);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Account found')
            ->assertSee('Send Reset Link')
            ->assertSee($user->email);

        $this->post(route('password.email'), ['recovery_email' => $user->email])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHas('success')
            ->assertSessionHas('password_reset_email', 'workflow-scholar@example.com');

        $code = null;
        $resetUrl = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code, &$resetUrl, $user) {
            $code = $mail->code;
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo($user->email)
                && preg_match('/^\d{6}$/', $mail->code) === 1
                && str_contains($mail->resetUrl, '/forgot-password/link');
        });

        $this->assertNotNull($code);
        $this->assertNotNull($resetUrl);

        $this->post(route('password.verify.post'), ['code' => $code])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('password_reset_verified', true);

        $this->post(route('password.update'), [
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'NewPass123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_admin_can_find_account_and_recover_via_verified_backup(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $admin = $this->makeAdmin();
        $backup = 'kcosinero1@ssct.edu.ph';
        $admin->backupEmails()->create([
            'email' => $backup,
            'verified_at' => now(),
        ]);

        $this->post(route('password.find'), ['email' => $backup])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(User::PERMANENT_ADMIN_EMAIL)
            ->assertSee($backup)
            ->assertSee('Verified backup email');

        $this->post(route('password.email'), ['recovery_email' => $backup])
            ->assertRedirect(route('password.verify'));

        $resetUrl = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$resetUrl, $backup) {
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo($backup) && $mail->resetUrl !== '';
        });

        $this->get($resetUrl)
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('password_reset_verified', true);

        $this->post(route('password.update'), [
            'password' => 'AdminBackup99',
            'password_confirmation' => 'AdminBackup99',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('AdminBackup99', $admin->fresh()->password));
    }

    public function test_unknown_email_shows_not_found_without_recovery_options(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.find'), ['email' => 'nobody-here@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error')
            ->assertSessionMissing(PasswordResetService::SESSION_ACCOUNT_ID);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('Send Reset Link');
    }

    public function test_cannot_send_reset_to_email_not_in_found_options(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('option-scholar@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email])
            ->assertRedirect(route('password.request'));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['recovery_email' => 'not-listed@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('recovery_email');

        Mail::assertNothingSent();
    }

    public function test_unverified_backup_cannot_be_used_to_find_or_recover(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $admin = $this->makeAdmin();
        $admin->backupEmails()->create([
            'email' => 'pending-backup@example.com',
            'verified_at' => null,
        ]);

        $this->post(route('password.find'), ['email' => 'pending-backup@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_expired_reset_link_cannot_be_used(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('expired-link@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->post(route('password.email'), ['recovery_email' => $user->email]);

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(11)]);

        $expiredUrl = URL::temporarySignedRoute(
            'password.reset.link',
            now()->addMinutes(10),
            ['email' => $user->email, 'code' => $code]
        );

        $this->get($expiredUrl)
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');
    }

    public function test_expired_code_cannot_be_verified(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('expired-scholar@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->post(route('password.email'), ['recovery_email' => $user->email])
            ->assertRedirect(route('password.verify'));

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(11)]);

        $this->from(route('password.verify'))
            ->post(route('password.verify.post'), ['code' => $code])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');
    }

    public function test_wrong_code_is_rejected_and_counted(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('wrong-code@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->post(route('password.email'), ['recovery_email' => $user->email]);

        $this->from(route('password.verify'))
            ->post(route('password.verify.post'), ['code' => '000000'])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');

        $this->assertSame(
            1,
            (int) DB::table('password_reset_tokens')->where('email', $user->email)->value('attempts')
        );
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $user = $this->makeScholar('same-pass@example.com', 'SamePass12');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->post(route('password.email'), ['recovery_email' => $user->email]);

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->post(route('password.verify.post'), ['code' => $code])
            ->assertRedirect(route('password.reset'));

        $this->from(route('password.reset'))
            ->post(route('password.update'), [
                'password' => 'SamePass12',
                'password_confirmation' => 'SamePass12',
            ])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('SamePass12', $user->fresh()->password));
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

    private function makeScholar(string $email, string $password): User
    {
        $program = ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-reset-'.uniqid(),
            'is_active' => true,
        ]);

        return User::register([
            'full_name' => 'Reset Workflow Scholar',
            'scholar_id' => 'SCH-RESET-'.uniqid(),
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'BSSA Administrator',
            'scholar_id' => User::PERMANENT_ADMIN_SCHOLAR_ID,
            'email' => User::PERMANENT_ADMIN_EMAIL,
            'password' => 'bssa_system2022',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_APPROVED,
        ]);
        $admin->forceFill([
            'is_admin' => true,
            'is_permanent' => true,
        ])->save();

        return $admin->fresh();
    }
}
