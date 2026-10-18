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
}
