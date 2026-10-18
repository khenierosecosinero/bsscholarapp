<?php

namespace App\Console\Commands;

use App\Services\MailConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InspectMailConfigCommand extends Command
{
    protected $signature = 'mail:inspect {--send= : Send a test message to this address}';

    protected $description = 'Show loaded SMTP settings without secrets, optionally send a test email';

    public function handle(MailConfigService $mailConfig): int
    {
        $mailConfig->syncRuntimeCredentials();
        $status = $mailConfig->status();
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');
        $from = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        $this->line('mailer='.$status['mailer']);
        $this->line('host='.$status['host']);
        $this->line('port='.$status['port']);
        $this->line('encryption='.($status['encryption'] ?? ''));
        $this->line('scheme='.($status['scheme'] ?? ''));
        $this->line('username_length='.strlen(trim($username)));
        $this->line('password_length='.strlen($password));
        $this->line('from_length='.strlen(trim($from)));
        $this->line('from_name='.$fromName);
        $this->line('username_looks_like_email='.(filter_var(trim($username), FILTER_VALIDATE_EMAIL) ? 'yes' : 'no'));
        $this->line('username_preview='.($status['username_preview'] ?? '(n/a)'));
        $this->line('username_source='.($status['username_source'] ?? '(n/a)'));
        $this->line('username_matches_expected='.(strcasecmp(trim($username), 'batangsurigaononscholarsapp@gmail.com') === 0 ? 'yes' : 'no'));
        $this->line('from_matches_username='.($status['from_matches_username'] ? 'yes' : 'no'));
        $this->line('ready='.($status['ready'] ? 'yes' : 'no'));
        $this->line('config_cached='.(app()->configurationIsCached() ? 'yes' : 'no'));
        $this->line('queue_connection='.config('queue.default'));

        foreach ($status['issues'] as $issue) {
            $this->warn('issue='.$issue);
        }

        if (app()->configurationIsCached()) {
            $this->comment('note=Configuration is cached. After editing .env mail settings, run: php artisan config:clear');
        }

        $to = $this->option('send');

        if (! is_string($to) || $to === '') {
            return $status['ready'] ? self::SUCCESS : self::FAILURE;
        }

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('send=failed');
            $this->error('Invalid --send email address.');

            return self::FAILURE;
        }

        if (! $status['ready']) {
            $this->error('send=failed');
            $this->error('SMTP is not ready. Fix the issues above first.');

            return self::FAILURE;
        }

        try {
            Mail::raw('BSSA SMTP test. Laravel reached Gmail SMTP at '.now()->toDateTimeString().'.', function ($message) use ($to) {
                $message->to($to)->subject('BSSA SMTP test');
            });
            $this->info('send=ok');
        } catch (Throwable $e) {
            $this->error('send=failed');
            $this->error('exception='.$e::class);
            $this->error($this->safeError($e));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function safeError(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, '534') || str_contains($raw, 'username') || str_contains($raw, 'password') || str_contains($raw, 'credentials')) {
            return 'Gmail rejected the SMTP login. Use a Google App Password in MAIL_PASSWORD, then run php artisan config:clear.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'Could not connect to smtp.gmail.com:587.';
        }

        return 'SMTP send failed.';
    }
}
