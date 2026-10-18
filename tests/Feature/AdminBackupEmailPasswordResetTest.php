<?php

namespace Tests\Feature;

use App\Mail\AdminBackupEmailVerificationMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\AdminBackupEmail;
use App\Models\User;
use App\Services\AdminBackupEmailService;
use App\Services\MailConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminBackupEmailPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_verify_edit_and_remove_multiple_backup_emails_without_password(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $admin = $this->makePermanentAdmin();
        $first = 'kcosinero1@ssct.edu.ph';
        $second = 'backup-two@example.com';

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('+ Backup Email')
            ->assertDontSee('Current Password (resend link)')
            ->assertDontSee('Current Password (remove)');

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => $first,
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => $second,
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame(2, $admin->backupEmails()->count());
        Mail::assertSent(AdminBackupEmailVerificationMail::class, 2);

        $backupOne = $admin->backupEmails()->where('email', $first)->first();
        $backupTwo = $admin->backupEmails()->where('email', $second)->first();
        $this->assertNotNull($backupOne);
        $this->assertNotNull($backupTwo);
        $this->assertFalse($backupOne->isVerified());

        Mail::fake();
        $this->fakeReadyMail();
        $this->post(route('logout'));
        $this->post(route('password.find'), ['email' => $first])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error');
        Mail::assertNothingSent();

        $this->verifyBackup($backupOne);
        $this->verifyBackup($backupTwo);
        $this->assertTrue($backupOne->fresh()->isVerified());
        $this->assertTrue($backupTwo->fresh()->isVerified());

        Mail::fake();
        $this->fakeReadyMail();
        $edited = 'backup-two-edited@example.com';
        $this->actingAs($admin)
            ->put(route('admin.settings.backup-emails.update', $backupTwo), [
                'backup_email' => $edited,
            ])
            ->assertRedirect(route('admin.settings'));

        $backupTwo->refresh();
        $this->assertSame($edited, $backupTwo->normalizedEmail());
        $this->assertFalse($backupTwo->isVerified());
        Mail::assertSent(AdminBackupEmailVerificationMail::class);

        $this->actingAs($admin)
            ->delete(route('admin.settings.backup-emails.destroy', $backupTwo))
            ->assertRedirect(route('admin.settings'));

        $this->assertDatabaseMissing('admin_backup_emails', ['id' => $backupTwo->id]);
        $this->assertSame(1, $admin->backupEmails()->count());

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Backup Recovery Emails')
            ->assertSee($first)
            ->assertSee('+ Backup Email');
    }

    public function test_rejects_invalid_and_duplicate_backup_emails(): void
    {
        Mail::fake();
        $this->fakeReadyMail();
        $admin = $this->makePermanentAdmin();

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'not-an-email',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('backup_email');

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => User::PERMANENT_ADMIN_EMAIL,
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('backup_email');

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'unique-backup@example.com',
            ])
            ->assertRedirect(route('admin.settings'));

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'unique-backup@example.com',
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHasErrors('backup_email');
    }

    public function test_guest_cannot_manage_backup_emails(): void
    {
        $admin = $this->makePermanentAdmin();
        $backup = $admin->backupEmails()->create([
            'email' => 'kcosinero1@ssct.edu.ph',
            'verified_at' => null,
        ]);

        $this->post(route('admin.settings.backup-emails.store'), [
            'backup_email' => 'intruder@example.com',
        ])->assertRedirect(route('login'));

        $this->put(route('admin.settings.backup-emails.update', $backup), [
            'backup_email' => 'changed@example.com',
        ])->assertRedirect(route('login'));

        $this->delete(route('admin.settings.backup-emails.destroy', $backup))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_recover_via_primary_and_each_verified_backup_email(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $admin = $this->makePermanentAdmin();
        $backups = [
            'kcosinero1@ssct.edu.ph',
            'second-backup@example.com',
        ];

        foreach ($backups as $email) {
            $row = $admin->backupEmails()->create([
                'email' => $email,
                'verified_at' => now(),
            ]);
            $this->assertTrue($row->isVerified());
        }

        $recoveryEmails = array_merge([User::PERMANENT_ADMIN_EMAIL], $backups);

        foreach ($recoveryEmails as $recoveryEmail) {
            Mail::fake();
            $this->fakeReadyMail();

            $this->post(route('password.find'), ['email' => $recoveryEmail])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('success');

            $this->post(route('password.email'), ['recovery_email' => $recoveryEmail])
                ->assertRedirect(route('password.verify'))
                ->assertSessionHas('success')
                ->assertSessionHas('password_reset_email', strtolower($recoveryEmail));

            $code = null;
            Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code, $recoveryEmail) {
                $code = $mail->code;

                return $mail->hasTo($recoveryEmail) && preg_match('/^\d{6}$/', $mail->code) === 1;
            });

            $this->post(route('password.verify.post'), ['code' => $code])
                ->assertRedirect(route('password.reset'));

            $newPassword = 'AdminNew-'.substr(md5($recoveryEmail), 0, 8);
            $this->post(route('password.update'), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])->assertRedirect(route('login'));

            $admin->refresh();
            $this->assertTrue(Hash::check($newPassword, $admin->password));

            $this->post(route('login.post'), [
                'email' => User::PERMANENT_ADMIN_EMAIL,
                'password' => $newPassword,
            ])->assertRedirect();
            $this->assertAuthenticatedAs($admin->fresh());
            $this->post(route('logout'));
        }
    }

    public function test_stage_default_backup_only_when_missing_and_wrong_token_fails(): void
    {
        Mail::fake();
        $admin = $this->makePermanentAdmin();

        $service = app(AdminBackupEmailService::class);
        $first = $service->stageDefaultPermanentAdminBackup($admin, sendMail: false);
        $second = $service->stageDefaultPermanentAdminBackup($admin->fresh(), sendMail: false);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $admin->backupEmails()->count());
        $this->assertSame(User::PERMANENT_ADMIN_BACKUP_EMAIL, $first->normalizedEmail());
        $this->assertFalse($first->isVerified());

        $badUrl = URL::temporarySignedRoute(
            'admin.backup-email.verify',
            now()->addMinutes(30),
            ['backupEmail' => $first->id, 'token' => 'not-the-real-token']
        );

        $this->get($badUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse($first->fresh()->isVerified());
    }

    public function test_unknown_email_shows_not_found_without_sending_mail(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $this->from(route('password.request'))
            ->post(route('password.find'), ['email' => 'nobody-here@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error')
            ->assertSessionMissing('password_reset_email');

        Mail::assertNothingSent();
    }

    public function test_expired_reset_code_cannot_complete_admin_recovery(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        $admin = $this->makePermanentAdmin();
        $admin->backupEmails()->create([
            'email' => User::PERMANENT_ADMIN_BACKUP_EMAIL,
            'verified_at' => now(),
        ]);

        $this->post(route('password.find'), ['email' => User::PERMANENT_ADMIN_BACKUP_EMAIL])
            ->assertRedirect(route('password.request'));

        $this->post(route('password.email'), ['recovery_email' => User::PERMANENT_ADMIN_BACKUP_EMAIL])
            ->assertRedirect(route('password.verify'));

        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        DB::table('password_reset_tokens')
            ->where('email', User::PERMANENT_ADMIN_BACKUP_EMAIL)
            ->update(['created_at' => now()->subMinutes(11)]);

        $this->from(route('password.verify'))
            ->post(route('password.verify.post'), ['code' => $code])
            ->assertRedirect(route('password.verify'))
            ->assertSessionHasErrors('code');
    }

    private function verifyBackup(AdminBackupEmail $backup): void
    {
        $plainToken = 'verify-token-'.uniqid();
        $backup->forceFill([
            'verify_token' => Hash::make($plainToken),
            'verify_sent_at' => now(),
            'verified_at' => null,
        ])->save();

        $url = URL::temporarySignedRoute(
            'admin.backup-email.verify',
            now()->addMinutes(30),
            ['backupEmail' => $backup->id, 'token' => $plainToken]
        );

        $this->get($url)->assertRedirect();
    }

    private function makePermanentAdmin(): User
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
}
