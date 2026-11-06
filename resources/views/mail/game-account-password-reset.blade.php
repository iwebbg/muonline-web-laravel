<x-mail::message :mail-branding="$mailBranding">
# {{ __('ui.recovery.mail_heading') }}

{{ __('ui.recovery.mail_intro', ['account' => $accountId]) }}

<x-mail::button :url="$resetUrl">
{{ __('ui.recovery.mail_button') }}
</x-mail::button>

{{ __('ui.recovery.mail_expiration', ['minutes' => $expiresInMinutes]) }}

{{ __('ui.recovery.mail_ignore') }}

{{ __('ui.recovery.mail_thanks') }},<br>
{{ $mailBranding->senderName }}
</x-mail::message>
