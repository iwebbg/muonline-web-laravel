<?php

namespace App\Mail;

use App\Website\WebsiteSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Carries the resolved website identity needed by transactional emails.
 */
final readonly class MailBranding
{
    /**
     * Creates one immutable branding snapshot for a message.
     */
    public function __construct(
        public string $brandTitle,
        public string $brandSubtitle,
        public string $senderName,
        public string $copyrightHolder,
        public string $homeUrl,
        public ?string $logoUrl,
    ) {}

    /**
     * Builds email branding from the currently published website identity.
     */
    public static function fromWebsiteSettings(WebsiteSettings $settings): self
    {
        $logoUrl = $settings->logoPath !== null
            ? Storage::disk('public')->url($settings->logoPath)
            : null;

        return new self(
            brandTitle: $settings->brandTitle,
            brandSubtitle: $settings->brandSubtitle,
            senderName: $settings->pageTitleSuffix,
            copyrightHolder: $settings->resolvedCopyrightHolder(),
            homeUrl: (string) config('app.url'),
            logoUrl: $logoUrl,
        );
    }
}
