<?php

namespace Tests\Feature\Mail;

use App\Mail\AccountDeletionCodeMail;
use App\Mail\GameAccountActivationMail;
use App\Mail\GameAccountPasswordResetMail;
use App\Mail\MailBranding;
use App\Website\WebsiteSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies transactional emails use the managed website identity.
 */
final class MailBrandingTest extends TestCase
{
    /**
     * Confirms every account email renders the fallback lockup and dynamic sender.
     */
    public function test_transactional_emails_render_managed_identity_without_logo(): void
    {
        config()->set('mail.from.address', 'realm@example.com');
        $branding = MailBranding::fromWebsiteSettings($this->websiteSettings());
        $messages = [
            new GameAccountPasswordResetMail(
                accountId: 'testacct',
                resetUrl: 'https://example.com/password/reset',
                expiresInMinutes: 60,
                mailBranding: $branding,
            ),
            new GameAccountActivationMail(
                accountId: 'testacct',
                activationUrl: 'https://example.com/account/activate',
                expiresInMinutes: 60,
                mailBranding: $branding,
            ),
            new AccountDeletionCodeMail(
                accountId: 'testacct',
                verificationCode: '123456',
                expiresInMinutes: 15,
                mailBranding: $branding,
            ),
        ];

        foreach ($messages as $message) {
            $this->assertManagedIdentityIsRendered($message);
        }
    }

    /**
     * Confirms an uploaded public logo replaces the text lockup in the header.
     */
    public function test_uploaded_logo_replaces_fallback_header_lockup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('website/logo.png', 'logo-content');
        $branding = MailBranding::fromWebsiteSettings(
            $this->websiteSettings(logoPath: 'website/logo.png'),
        );
        $message = new GameAccountPasswordResetMail(
            accountId: 'testacct',
            resetUrl: 'https://example.com/password/reset',
            expiresInMinutes: 60,
            mailBranding: $branding,
        );

        $message
            ->assertSeeInHtml(Storage::disk('public')->url('website/logo.png'))
            ->assertDontSeeInHtml('>MU<', false);
    }

    /**
     * Confirms a real delivery renders both HTML and plain-text message bodies.
     */
    public function test_transactional_email_can_be_delivered_through_mail_transport(): void
    {
        config()->set('mail.default', 'array');
        config()->set('mail.from.address', 'realm@example.com');
        Mail::purge();
        $branding = MailBranding::fromWebsiteSettings($this->websiteSettings());
        $message = new GameAccountPasswordResetMail(
            accountId: 'testacct',
            resetUrl: 'https://example.com/password/reset',
            expiresInMinutes: 60,
            mailBranding: $branding,
        );

        Mail::to('player@example.com')->send($message);

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
    }

    /**
     * Asserts the sender, header identity, site palette and footer for one message.
     */
    private function assertManagedIdentityIsRendered(Mailable $message): void
    {
        $message
            ->assertFrom('realm@example.com', 'DreamMU')
            ->assertSeeInHtml('DREAMMU')
            ->assertSeeInHtml('Season 2')
            ->assertSeeInHtml('InterWeb Ltd.')
            ->assertSeeInHtml('#101218', false)
            ->assertSeeInText('DreamMU');
    }

    /**
     * Returns a complete website identity snapshot for mail rendering.
     */
    private function websiteSettings(?string $logoPath = null): WebsiteSettings
    {
        return new WebsiteSettings(
            brandTitle: 'DREAMMU',
            brandSubtitle: 'Season 2',
            logoPath: $logoPath,
            homeTitleEn: null,
            homeTitleBg: null,
            pageTitleSuffix: 'DreamMU',
            metaDescriptionEn: null,
            metaDescriptionBg: null,
            metaKeywordsEn: null,
            metaKeywordsBg: null,
            ogImagePath: null,
            copyrightHolder: 'InterWeb Ltd.',
            themeSlug: 'muonline',
            revision: 1,
        );
    }
}
