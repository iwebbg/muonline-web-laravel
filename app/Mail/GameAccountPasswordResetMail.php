<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers one short-lived game account password-reset link.
 */
final class GameAccountPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Creates the message without embedding any game password value.
     */
    public function __construct(
        private readonly string $accountId,
        private readonly string $resetUrl,
        private readonly int $expiresInMinutes,
    ) {}

    /**
     * Defines the localized recovery message subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ui.recovery.mail_subject'),
        );
    }

    /**
     * Provides only the account identifier, reset URL and expiration window.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.game-account-password-reset',
            with: [
                'accountId' => $this->accountId,
                'resetUrl' => $this->resetUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    /**
     * Confirms password-recovery mail never contains attachments.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
