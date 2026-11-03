<x-mail::message>
# {{ __('ui.activation.mail_heading') }}

{{ __('ui.activation.mail_intro', ['account' => $accountId]) }}

<x-mail::button :url="$activationUrl">
{{ __('ui.activation.mail_button') }}
</x-mail::button>

{{ __('ui.activation.mail_expiry', ['minutes' => $expiresInMinutes]) }}

{{ __('ui.activation.mail_ignore') }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
