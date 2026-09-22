<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InspectMailConfigCommand extends Command
{
    protected $signature = 'mail:inspect {--send= : Send a test message to this address}';

    protected $description = 'Show loaded SMTP settings without secrets, optionally send a test email';

    public function handle(): int
    {
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');
        $from = (string) config('mail.from.address');

        $this->line('mailer='.config('mail.default'));
        $this->line('host='.config('mail.mailers.smtp.host'));
        $this->line('port='.config('mail.mailers.smtp.port'));
        $this->line('encryption='.config('mail.mailers.smtp.encryption'));
        $this->line('scheme='.config('mail.mailers.smtp.scheme'));
        $this->line('username_length='.strlen($username));
        $this->line('password_length='.strlen($password));
        $this->line('from_length='.strlen($from));
        $this->line('username_looks_like_email='.(filter_var($username, FILTER_VALIDATE_EMAIL) ? 'yes' : 'no'));

        $to = $this->option('send');

        if (! is_string($to) || $to === '') {
            return self::SUCCESS;
        }

        try {
            Mail::raw('BSSA SMTP test. Laravel reached Gmail SMTP.', function ($message) use ($to) {
                $message->to($to)->subject('BSSA SMTP test');
            });
            $this->info('send=ok');
        } catch (Throwable $e) {
            $this->error('send=failed');
            $this->error($this->safeError($e));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function safeError(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, '534') || str_contains($raw, 'username') || str_contains($raw, 'password') || str_contains($raw, 'credentials')) {
            return 'Gmail rejected the SMTP login.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'Could not connect to smtp.gmail.com:587.';
        }

        return 'SMTP send failed.';
    }
}
