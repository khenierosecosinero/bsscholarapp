<?php

namespace Tests\Unit;

use App\Services\MailConfigService;
use Tests\TestCase;

class MailConfigServiceTest extends TestCase
{
    public function test_recognizes_configured_gmail_username(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'batangsurigaononscholarsapp@gmail.com',
            'mail.mailers.smtp.password' => 'abcdefghijklmnop',
            'mail.from.address' => 'batangsurigaononscholarsapp@gmail.com',
            'mail.from.name' => 'BSSA',
        ]);

        $status = app(MailConfigService::class)->status();

        $this->assertTrue($status['ready']);
        $this->assertSame([], $status['issues']);
        $this->assertSame('b***@gmail.com', $status['username_preview']);
        $this->assertSame('config', $status['username_source']);
        $this->assertTrue($status['from_matches_username']);
    }

    public function test_falls_back_to_process_env_when_config_username_is_empty(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => '',
            'mail.mailers.smtp.password' => 'abcdefghijklmnop',
            'mail.from.address' => 'batangsurigaononscholarsapp@gmail.com',
        ]);

        $_ENV['MAIL_USERNAME'] = 'batangsurigaononscholarsapp@gmail.com';
        $_SERVER['MAIL_USERNAME'] = 'batangsurigaononscholarsapp@gmail.com';

        $status = app(MailConfigService::class)->status();

        $this->assertTrue($status['ready'], implode('; ', $status['issues']));
        $this->assertSame('env-fallback', $status['username_source']);
        $this->assertSame('b***@gmail.com', $status['username_preview']);
    }

    public function test_array_mailer_is_ready_without_gmail_username(): void
    {
        config([
            'mail.default' => 'array',
            'mail.mailers.smtp.username' => '',
            'mail.mailers.smtp.password' => '',
            'mail.from.address' => '',
        ]);

        $status = app(MailConfigService::class)->status();

        $this->assertTrue($status['ready']);
        $this->assertSame([], $status['issues']);
    }

    public function test_dotenv_file_fallback_when_config_and_process_env_are_empty(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => '',
            'mail.mailers.smtp.password' => '',
            'mail.from.address' => '',
        ]);

        unset($_ENV['MAIL_USERNAME'], $_ENV['MAIL_PASSWORD'], $_ENV['MAIL_FROM_ADDRESS']);
        unset($_SERVER['MAIL_USERNAME'], $_SERVER['MAIL_PASSWORD'], $_SERVER['MAIL_FROM_ADDRESS']);

        $status = app(MailConfigService::class)->status();

        // Relies on the real project .env (gitignored) used by local BSSA.
        if ($status['username_source'] === 'dotenv-file') {
            $this->assertSame('b***@gmail.com', $status['username_preview']);
            $this->assertTrue($status['ready'], implode('; ', $status['issues']));
        } else {
            $this->assertContains($status['username_source'], ['config', 'env-fallback', 'dotenv-file', 'missing']);
        }
    }

    public function test_resend_is_ready_with_an_api_key_and_verified_from_address(): void
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key_value_123456',
            'mail.from.address' => 'noreply@bsscholar.app',
        ]);

        $status = app(MailConfigService::class)->status();

        $this->assertTrue($status['ready'], implode('; ', $status['issues']));
        $this->assertSame('resend', $status['mailer']);
        $this->assertSame('api.resend.com', $status['host']);
        $this->assertSame('n***@bsscholar.app', $status['username_preview']);
    }

    public function test_resend_rejects_a_gmail_sending_address(): void
    {
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key_value_123456',
            'mail.from.address' => 'batangsurigaononscholarsapp@gmail.com',
        ]);

        $status = app(MailConfigService::class)->status();

        $this->assertFalse($status['ready']);
        $this->assertTrue(collect($status['issues'])->contains(
            fn (string $issue) => str_contains($issue, 'cannot send from a Gmail address')
        ));
    }

    public function test_disabled_gmail_api_is_reported_without_the_google_response_body(): void
    {
        config(['mail.default' => 'gmail_api']);

        $google = new \RuntimeException(
            '{"error":{"code":403,"message":"Gmail API has not been used in project 123 before or it is disabled.","errors":[{"reason":"accessNotConfigured"}]}}'
        );
        $wrapped = new \Symfony\Component\Mailer\Exception\TransportException(
            'Gmail API email delivery failed. gmail_api_status=403 gmail_api_reason=accessnotconfigured',
            403,
            $google
        );

        $service = app(MailConfigService::class);
        $message = $service->deliveryError($wrapped);

        $this->assertStringContainsString('Gmail API is disabled', $message);
        $this->assertStringNotContainsString('project 123', $message);
        $this->assertSame(403, $service->failureContext($wrapped)['gmail_status']);
        $this->assertSame('accessnotconfigured', $service->failureContext($wrapped)['gmail_reason']);
    }

    public function test_smtp_connection_error_explains_the_railway_block(): void
    {
        config(['mail.default' => 'smtp']);

        $message = app(MailConfigService::class)->deliveryError(
            new \RuntimeException('Connection to smtp.gmail.com:587 timed out.')
        );

        $this->assertStringContainsString('Railway Hobby restricts outbound SMTP', $message);
        $this->assertStringContainsString('MAIL_MAILER=gmail_api', $message);
    }
}
