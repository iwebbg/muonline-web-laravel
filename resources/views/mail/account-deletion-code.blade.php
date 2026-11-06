<x-mail::message :mail-branding="$mailBranding">
# {{ __('ui.account.deletion_mail_heading') }}

{{ __('ui.account.deletion_mail_intro', ['account' => $accountId]) }}

<x-mail::panel>
{{ $verificationCode }}
</x-mail::panel>

{{ __('ui.account.deletion_mail_expiry', ['minutes' => $expiresInMinutes]) }}

{{ __('ui.account.deletion_mail_ignore') }}

{{ __('ui.recovery.mail_thanks') }},<br>
{{ $mailBranding->senderName }}
</x-mail::message>
