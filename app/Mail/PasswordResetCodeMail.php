<?php

namespace App\Mail;

use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your BSSA password reset verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset-code',
            with: [
                'name' => $this->user->full_name,
                'code' => $this->code,
                'minutes' => PasswordResetService::EXPIRE_MINUTES,
            ],
        );
    }
}
