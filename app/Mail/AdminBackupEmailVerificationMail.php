<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminBackupEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $pendingEmail,
        public string $verificationUrl,
        public int $expireMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your BSSA administrator backup recovery email',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-backup-email-verification',
            with: [
                'name' => $this->user->full_name,
                'verificationUrl' => $this->verificationUrl,
                'expireMinutes' => $this->expireMinutes,
                'pendingEmail' => $this->pendingEmail,
            ],
        );
    }
}
