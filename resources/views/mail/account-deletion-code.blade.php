<x-mail::message>
# {{ __('ui.account.deletion_mail_heading') }}

{{ __('ui.account.deletion_mail_intro', ['account' => $accountId]) }}

<x-mail::panel>
{{ $verificationCode }}
</x-mail::panel>

{{ __('ui.account.deletion_mail_expiry', ['minutes' => $expiresInMinutes]) }}

{{ __('ui.account.deletion_mail_ignore') }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
