<?php

namespace App\Services;

use App\Mail\WorkflowNotificationMail;
use App\Models\User;
use App\Support\OutboundAppUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Throwable;

class WorkflowMailService
{
    /**
     * Account and security messages ignore optional notification preferences.
     *
     * @var list<string>
     */
    private const ESSENTIAL_TITLES = [
        'Account Pending Approval',
        'Welcome to BSSA',
        'Account Approved',
        'Account Rejected',
        'Account Suspended',
        'Account Blocked',
        'Account Reactivated',
        'Scholar Staff Account Pending Approval',
        'Scholar Staff Account Created',
        'Scholar Staff Account Approved',
        'Scholar Staff Account Activated',
        'Scholar Staff Account Deactivated',
        'Scholar Staff Account Rejected',
        'Password Changed',
    ];

    public function sendForNotification(User $user, string $title, string $body, string $category, ?int $eventId = null): void
    {
        if (! $this->allows($user, $category, $title)) {
            return;
        }

        [$url, $label] = $this->actionFor($user, $category, $title);
        $ttl = $title === 'Password Changed' ? now()->addSeconds(90) : now()->addDays(90);

        $this->send(
            $user->email,
            $user->full_name ?: 'BSSA user',
            $title,
            $body,
            $url,
            $label,
            $this->dedupeKey('user', (string) $user->id, $title, $body),
            $ttl
        );
    }

    public function sendToAddress(
        ?string $email,
        string $name,
        string $title,
        string $body,
        string $actionUrl,
        string $actionLabel,
        string $dedupeKey
    ): void {
        $this->send($email, $name, $title, $body, $actionUrl, $actionLabel, 'bssa-mail:'.$dedupeKey, now()->addDays(90));
    }

    public function notifyReviewersOfScholar(User $scholar): void
    {
        if (! $scholar->isScholar() || ! $scholar->isPendingApproval()) {
            return;
        }

        $coverage = $scholar->coverageLabel();
        $title = 'New scholar registration';
        $body = "{$scholar->full_name} ({$scholar->scholar_id}) registered for {$coverage} and is waiting for review.";
        [$url, $label] = OutboundAppUrl::withRoot(fn () => [route('staff.approval-requests'), 'Review registrations']);

        $this->approvedStaffFor($scholar)->each(function (User $staff) use ($title, $body, $url, $label, $scholar) {
            $this->send(
                $staff->email,
                $staff->full_name ?: 'Scholar Staff',
                $title,
                $body,
                $url,
                $label,
                $this->dedupeKey('reviewer', (string) $staff->id, $title, 'scholar:'.$scholar->id),
                now()->addDays(30)
            );
        });

        [$adminUrl, $adminLabel] = OutboundAppUrl::withRoot(fn () => [route('admin.scholars'), 'Open scholars']);
        $this->alertAdministrators('new-scholar:'.$scholar->id, $title, $body, $adminUrl, $adminLabel, now()->addDays(30));
    }

    public function notifyAdministratorsOfStaff(User $staff): void
    {
        if (! $staff->isScholarStaff() || ! $staff->isStaffPendingApproval()) {
            return;
        }

        $coverage = $staff->coverageLabel();
        [$url, $label] = OutboundAppUrl::withRoot(fn () => [route('admin.staff'), 'Review staff']);
        $this->alertAdministrators(
            'new-staff:'.$staff->id,
            'New scholar staff registration',
            "{$staff->full_name} registered as Scholar Staff for {$coverage} and is waiting for administrator approval.",
            $url,
            $label,
            now()->addDays(30)
        );
    }

    public function alertAdministrators(
        string $key,
        string $title,
        string $body,
        ?string $actionUrl = null,
        string $actionLabel = 'Open BSSA',
        $ttl = null
    ): void {
        if (! Cache::add('bssa-admin-alert:'.$key, 1, $ttl ?? now()->addHours(6))) {
            return;
        }

        [$fallbackUrl] = OutboundAppUrl::withRoot(fn () => [route('admin.dashboard'), 'Open admin']);
        $url = $actionUrl ?: $fallbackUrl;

        $this->administrators()->each(function (User $admin) use ($title, $body, $url, $actionLabel, $key) {
            $this->send(
                $admin->email,
                $admin->full_name ?: 'Administrator',
                $title,
                $body,
                $url,
                $actionLabel,
                $this->dedupeKey('admin', (string) $admin->id, $title, $key),
                now()->addDays(30),
                alertOnFailure: false
            );
        });
    }

    /**
     * Used by tests to prove a rolled-back transaction does not send mail.
     */
    public function deliverAfterCommit(callable $callback): void
    {
        $this->afterCommit($callback);
    }

    private function send(
        ?string $email,
        string $name,
        string $title,
        string $body,
        string $actionUrl,
        string $actionLabel,
        string $dedupeKey,
        $ttl,
        bool $alertOnFailure = true
    ): void {
        $email = strtolower(trim((string) $email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Workflow email skipped because the recipient address is invalid.', [
                'title' => $title,
            ]);

            return;
        }

        $this->afterCommit(function () use ($email, $name, $title, $body, $actionUrl, $actionLabel, $dedupeKey, $ttl, $alertOnFailure) {
            if (! Cache::add($dedupeKey, 1, $ttl)) {
                return;
            }

            $sent = $this->attemptSend($email, $name, $title, $body, $actionUrl, $actionLabel);

            if ($sent) {
                return;
            }

            Cache::forget($dedupeKey);

            if ($alertOnFailure) {
                $this->recordDeliveryFailure();
            }
        });
    }

    private function attemptSend(
        string $email,
        string $name,
        string $title,
        string $body,
        string $actionUrl,
        string $actionLabel
    ): bool {
        $mailable = new WorkflowNotificationMail($name, $title, $body, $actionUrl, $actionLabel);
        $attempts = 0;

        while ($attempts < 2) {
            $attempts++;

            try {
                $this->mailer()->to($email)->send($mailable);

                Log::info('Workflow email accepted by the mail transport.', [
                    'title' => $title,
                    'recipient_domain' => substr((string) strrchr($email, '@'), 1),
                    'attempt' => $attempts,
                    'queued' => false,
                ]);

                return true;
            } catch (Throwable $e) {
                Log::error('Workflow email was not accepted by the mail transport.', [
                    'title' => $title,
                    'recipient_domain' => substr((string) strrchr($email, '@'), 1),
                    'attempt' => $attempts,
                    'exception' => $e::class,
                    'reason' => $this->safeError($e),
                ]);
            }
        }

        return false;
    }

    private function recordDeliveryFailure(): void
    {
        $bucket = 'bssa-mail-failures:'.now()->format('YmdH');
        $count = (int) Cache::increment($bucket);
        Cache::put($bucket, $count, now()->addHours(2));

        if ($count < 3) {
            return;
        }

        $this->alertAdministrators(
            'mail-failures:'.now()->format('YmdH'),
            'Email delivery is failing',
            'BSSA could not hand several notification emails to the mail server during this hour. Check the mail settings and the Laravel log. In-app notifications are still saved.',
            null,
            'Open admin',
            now()->addHours(6)
        );
    }

    private function afterCommit(callable $callback): void
    {
        if (app()->runningUnitTests() && ! config('mail.force_after_commit')) {
            $callback();

            return;
        }

        if (DB::transactionLevel() === 0) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    private function allows(User $user, string $category, string $title): bool
    {
        if (in_array($title, self::ESSENTIAL_TITLES, true)) {
            return true;
        }

        $preference = match ($category) {
            'documents' => 'document_updates',
            'attendance' => 'attendance_updates',
            'service_hours' => 'service_hours',
            'event_reminder' => 'event_reminders',
            default => null,
        };

        if ($preference === null) {
            return false;
        }

        return (bool) ($user->notificationPreferences()[$preference] ?? true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function actionFor(User $user, string $category, string $title): array
    {
        return OutboundAppUrl::withRoot(function () use ($user, $category, $title) {
            if (in_array($title, ['Account Suspended', 'Account Blocked', 'Account Rejected', 'Scholar Staff Account Rejected', 'Scholar Staff Account Deactivated'], true)) {
                return [route('login'), 'Go to sign in'];
            }

            if ($user->isAdmin()) {
                return [route('admin.dashboard'), 'Open admin'];
            }

            if ($user->isScholarStaff()) {
                $route = match ($category) {
                    'documents' => 'staff.documents',
                    'event_reminder', 'attendance', 'service_hours' => 'staff.events',
                    default => 'staff.dashboard',
                };

                return [route($route), 'Open Scholar Staff'];
            }

            $route = match ($category) {
                'documents' => 'user.documents',
                'event_reminder', 'attendance' => 'user.events',
                'service_hours' => 'user.service-hours',
                default => 'user.notifications',
            };

            return [route($route), 'View in BSSA'];
        });
    }

    private function approvedStaffFor(User $scholar)
    {
        return User::query()
            ->where('role', User::ROLE_SCHOLAR_STAFF)
            ->where('status', User::STATUS_APPROVED)
            ->where('scholarship_program_id', $scholar->scholarship_program_id)
            ->when($scholar->scholarship_club_id, function ($query) use ($scholar) {
                $query->where(function ($staff) use ($scholar) {
                    $staff->where('scholarship_club_id', $scholar->scholarship_club_id)
                        ->orWhereNull('scholarship_club_id');
                });
            })
            ->get();
    }

    private function administrators()
    {
        return User::query()
            ->where(function ($query) {
                $query->where('is_admin', true)->orWhere('role', User::ROLE_ADMIN);
            })
            ->get();
    }

    private function dedupeKey(string $scope, string $id, string $title, string $body): string
    {
        return 'bssa-mail:'.hash('sha256', $scope.'|'.$id.'|'.$title.'|'.$body);
    }

    private function mailer()
    {
        app(MailConfigService::class)->syncRuntimeCredentials();

        if (Mail::getFacadeRoot() instanceof MailFake) {
            return Mail::mailer();
        }

        $default = strtolower(trim((string) config('mail.default')));

        if (in_array($default, ['array', 'log'], true)) {
            return Mail::mailer($default);
        }

        return Mail::mailer('smtp');
    }

    private function safeError(Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, '534') || str_contains($raw, 'username') || str_contains($raw, 'password') || str_contains($raw, 'credentials')) {
            return 'The mail server rejected the SMTP login.';
        }

        if (str_contains($raw, 'connect') || str_contains($raw, 'timed out') || str_contains($raw, 'timeout') || str_contains($raw, 'connection refused')) {
            return 'The mail server could not be reached.';
        }

        return 'The mail server did not accept the message.';
    }
}
