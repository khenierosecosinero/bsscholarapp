<?php

namespace Tests\Feature;

use App\Mail\AdminBackupEmailVerificationMail;
use App\Models\User;
use App\Services\MailConfigService;
use App\Support\LanAddress;
use App\Support\OutboundAppUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminBackupEmailVerificationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_link_uses_configured_public_url_not_loopback(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        config([
            'app.url' => 'http://127.0.0.1:8000',
            'app.public_url' => 'http://192.168.1.50:8000',
        ]);

        $admin = $this->makePermanentAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'verify-link@example.com',
            ])
            ->assertRedirect(route('admin.settings'));

        Mail::assertSent(AdminBackupEmailVerificationMail::class, function (AdminBackupEmailVerificationMail $mail) {
            $this->assertStringStartsWith('http://192.168.1.50:8000/backup-email/verify/', $mail->verificationUrl);
            $this->assertStringNotContainsString('127.0.0.1', $mail->verificationUrl);
            $this->assertStringNotContainsString('localhost', $mail->verificationUrl);
            $this->assertStringContainsString('signature=', $mail->verificationUrl);
            $this->assertStringContainsString('token=', $mail->verificationUrl);

            return $mail->hasTo('verify-link@example.com');
        });
    }

    public function test_opening_signed_verification_link_marks_only_that_backup_verified(): void
    {
        Mail::fake();
        $this->fakeReadyMail();

        config([
            'app.url' => 'http://127.0.0.1:8000',
            'app.public_url' => 'http://127.0.0.1:8000',
            'app.env' => 'testing',
        ]);

        $admin = $this->makePermanentAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'first-verify@example.com',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.backup-emails.store'), [
                'backup_email' => 'second-verify@example.com',
            ]);

        $url = null;
        Mail::assertSent(AdminBackupEmailVerificationMail::class, function (AdminBackupEmailVerificationMail $mail) use (&$url) {
            if ($mail->pendingEmail === 'first-verify@example.com') {
                $url = $mail->verificationUrl;
            }

            return true;
        });

        $this->assertNotNull($url);

        $this->get($url)
            ->assertRedirect()
            ->assertSessionHas('success');

        $first = $admin->backupEmails()->where('email', 'first-verify@example.com')->first();
        $second = $admin->backupEmails()->where('email', 'second-verify@example.com')->first();

        $this->assertTrue($first->fresh()->isVerified());
        $this->assertFalse($second->fresh()->isVerified());

        // Single-use: opening again fails.
        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_outbound_app_url_prefers_public_url_over_loopback_app_url(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'app.public_url' => 'http://10.0.0.8:8000',
        ]);

        $this->assertSame('http://10.0.0.8:8000', OutboundAppUrl::root());
        $this->assertFalse(LanAddress::isLoopbackOrWildcard(parse_url(OutboundAppUrl::root(), PHP_URL_HOST)));
    }

    private function makePermanentAdmin(): User
    {
        $admin = User::register([
            'full_name' => 'BSSA Administrator',
            'scholar_id' => User::PERMANENT_ADMIN_SCHOLAR_ID.'-LINK',
            'email' => 'admin-link-'.uniqid().'@example.com',
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
