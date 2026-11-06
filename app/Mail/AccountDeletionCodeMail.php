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
 * Delivers the short-lived code that verifies an account deletion request.
 */
final class AccountDeletionCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Creates the message without persisting or logging the plaintext code.
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string $verificationCode,
        public readonly int $expiresInMinutes,
        private readonly MailBranding $mailBranding,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: $this->mailBranding->senderName,
            ),
            subject: __('ui.account.deletion_mail_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-deletion-code',
            with: [
                'mailBranding' => $this->mailBranding,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
