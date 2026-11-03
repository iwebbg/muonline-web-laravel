<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers one short-lived game account email activation link.
 */
final class GameAccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Creates the message without embedding the game account password.
     */
    public function __construct(
        private readonly string $accountId,
        private readonly string $activationUrl,
        private readonly int $expiresInMinutes,
    ) {}

    /**
     * Defines the localized activation message subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ui.activation.mail_subject'),
        );
    }

    /**
     * Provides only the account identifier, activation URL and expiry window.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.game-account-activation',
            with: [
                'accountId' => $this->accountId,
                'activationUrl' => $this->activationUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    /**
     * Confirms activation email never contains attachments.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
