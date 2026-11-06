<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        private readonly MailBranding $mailBranding,
    ) {}

    /**
     * Defines the localized recovery message subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: $this->mailBranding->senderName,
            ),
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
                'mailBranding' => $this->mailBranding,
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
