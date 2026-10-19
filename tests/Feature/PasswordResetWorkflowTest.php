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
            ->assertSee('Account found. Select a recovery email:')
            ->assertSee('Send Reset Link')
            ->assertSee('Name:')
            ->assertSee($user->full_name)
            ->assertSee('Email:')
            ->assertSee($user->email)
            ->assertSee('Primary email')
            ->assertSee('value="'.$user->email.'"', false)
            ->assertSee('Enter your registered email address', false)
            ->assertDontSee('cosinerok@gmail.com');

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
            ->assertSee('Name:')
            ->assertSee($admin->full_name)
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

    public function test_forgot_password_form_starts_empty_without_hardcoded_account_data(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Enter your registered email address', false)
            ->assertSee('Find Account')
            ->assertDontSee('cosinerok@gmail.com')
            ->assertDontSee('Account found. Select a recovery email:')
            ->assertDontSee('Send Reset Link')
            ->assertDontSee('Name:')
            ->assertDontSee('id="recovery-options"', false)
            ->assertDontSee('Primary email');
    }

    public function test_fresh_forgot_password_link_clears_prior_recovery_section(): void
    {
        $user = $this->makeScholar('fresh-start@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->get(route('password.request'))
            ->assertSee('Account found. Select a recovery email:')
            ->assertSee('Send Reset Link');

        $this->get(route('password.request', ['fresh' => 1]))
            ->assertRedirect(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSessionMissing(PasswordResetService::SESSION_RECOVERY_OPTIONS)
            ->assertDontSee('Account found. Select a recovery email:')
            ->assertDontSee('Send Reset Link')
            ->assertDontSee($user->full_name)
            ->assertDontSee('id="recovery-options"', false);
    }

    public function test_unknown_email_shows_not_found_without_recovery_options(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.find'), ['email' => 'nobody-here@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'No account was found with the email address you entered.')
            ->assertSessionMissing(PasswordResetService::SESSION_ACCOUNT_ID);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('No account was found with the email address you entered.')
            ->assertDontSee('Account found. Select a recovery email:')
            ->assertDontSee('Send Reset Link')
            ->assertDontSee('Name:')
            ->assertDontSee('id="recovery-options"', false);
    }

    public function test_failed_search_after_success_hides_previous_recovery_section(): void
    {
        $user = $this->makeScholar('had-account@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->get(route('password.request'))
            ->assertSee($user->full_name)
            ->assertSee('Send Reset Link');

        $this->from(route('password.request'))
            ->post(route('password.find'), ['email' => 'missing-now@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error')
            ->assertSessionMissing(PasswordResetService::SESSION_RECOVERY_OPTIONS);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('Account found. Select a recovery email:')
            ->assertDontSee('Send Reset Link')
            ->assertDontSee($user->full_name)
            ->assertDontSee('id="recovery-options"', false)
            ->assertDontSee('value="missing-now@example.com"', false)
            ->assertDontSee('value="'.$user->email.'"', false);
    }

    public function test_reopening_forgot_password_does_not_restore_prior_find(): void
    {
        $user = $this->makeScholar('reopen-find@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email]);
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Account found. Select a recovery email:')
            ->assertSee($user->full_name);

        // Second open (refresh / revisit) has no reveal flash — must render a blank form.
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSessionMissing(PasswordResetService::SESSION_ACCOUNT_ID)
            ->assertSessionMissing(PasswordResetService::SESSION_RECOVERY_OPTIONS)
            ->assertSessionMissing(PasswordResetService::SESSION_LOOKUP_EMAIL)
            ->assertDontSee('Account found. Select a recovery email:')
            ->assertDontSee('Send Reset Link')
            ->assertDontSee($user->full_name)
            ->assertDontSee('value="'.$user->email.'"', false)
            ->assertDontSee('id="recovery-options"', false)
            ->assertDontSee('cosinerok@gmail.com');
    }

    public function test_empty_email_is_validated_before_search(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.find'), ['email' => ''])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_search_again_clears_previous_account_information(): void
    {
        $user = $this->makeScholar('search-again@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $user->email])
            ->assertSessionHas(PasswordResetService::SESSION_ACCOUNT_ID, $user->id);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee($user->full_name)
            ->assertSee($user->email);

        $this->get(route('password.request', ['again' => 1]))
            ->assertRedirect(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSessionMissing(PasswordResetService::SESSION_ACCOUNT_ID)
            ->assertSessionMissing(PasswordResetService::SESSION_ACCOUNT_NAME)
            ->assertSessionMissing(PasswordResetService::SESSION_LOOKUP_EMAIL)
            ->assertSessionMissing(PasswordResetService::SESSION_RECOVERY_OPTIONS)
            ->assertDontSee('Account found')
            ->assertDontSee($user->full_name)
            ->assertDontSee('value="'.$user->email.'"', false)
            ->assertDontSee('Send Reset Link');
    }

    public function test_new_find_replaces_previous_account_information(): void
    {
        $first = $this->makeScholar('first-find@example.com', 'OldPass123');
        $second = $this->makeScholar('second-find@example.com', 'OldPass123');

        $this->post(route('password.find'), ['email' => $first->email]);
        $this->get(route('password.request'))
            ->assertSee($first->full_name)
            ->assertSee($first->email);

        $this->post(route('password.find'), ['email' => $second->email])
            ->assertSessionHas(PasswordResetService::SESSION_ACCOUNT_ID, $second->id);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee($second->full_name)
            ->assertSee($second->email)
            ->assertSee('value="'.$second->email.'"', false)
            ->assertDontSee('value="'.$first->email.'"', false);
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
