<?php

namespace App\Jobs;

use App\Services\WorkflowMailService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkflowMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120];

    public function __construct(
        public string $email,
        public string $name,
        public string $title,
        public string $body,
        public string $actionUrl,
        public string $actionLabel,
        public string $dedupeKey,
        public DateTimeInterface $expiresAt,
        public bool $alertOnFailure = true,
    ) {}

    public function handle(WorkflowMailService $mail): void
    {
        $mail->deliverQueuedEmail(
            $this->email,
            $this->name,
            $this->title,
            $this->body,
            $this->actionUrl,
            $this->actionLabel,
            $this->dedupeKey,
            $this->expiresAt,
            $this->alertOnFailure,
        );
    }
}
