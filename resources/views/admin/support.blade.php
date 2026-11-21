@extends('layouts.admin')

@section('title', __('ui.support.navigation'))

@section('content')
    <div class="admin-shell grid gap-6 py-10 lg:py-14">
        <header>
            <p class="eyebrow">{{ __('ui.support.eyebrow') }}</p>
            <h1 class="mt-4 font-display text-4xl text-white sm:text-5xl">{{ __('ui.support.navigation') }}</h1>
            <p class="mt-3 max-w-3xl leading-7 text-silver">{{ __('ui.support.description') }}</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel-card grid content-start gap-4 p-6 sm:p-8" aria-labelledby="support-issues-heading">
                <p class="kicker">GitHub</p>
                <h2 id="support-issues-heading" class="text-2xl font-semibold text-white">{{ __('ui.support.report_problem') }}</h2>
                <p class="leading-7 text-silver">{{ __('ui.support.report_help') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a class="button button-primary" href="https://github.com/iwebbg/muonline-web-laravel/issues/new" target="_blank" rel="noopener noreferrer">{{ __('ui.support.open_issue') }} ↗</a>
                    <a class="button button-ghost" href="https://github.com/iwebbg/muonline-web-laravel" target="_blank" rel="noopener noreferrer">{{ __('ui.support.repository') }} ↗</a>
                </div>
            </section>
            <section class="panel-card grid content-start gap-4 p-6 sm:p-8" aria-labelledby="support-contact-heading">
                <p class="kicker">InterWeb Ltd.</p>
                <h2 id="support-contact-heading" class="text-2xl font-semibold text-white">{{ __('ui.support.contact') }}</h2>
                <p class="leading-7 text-silver">{{ __('ui.support.contact_help') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a class="button button-ghost" href="mailto:office@interweb.bg?subject=MuOnline%20Web%20support">office@interweb.bg ↗</a>
                    <a class="button button-ghost" href="https://github.com/iwebbg/muonline-web-laravel/blob/main/docs/README.md" target="_blank" rel="noopener noreferrer">{{ __('ui.support.documentation') }} ↗</a>
                </div>
            </section>
        </div>

        <section class="panel-card grid gap-4 p-6 sm:p-8" aria-labelledby="support-diagnostics-heading">
            <p class="kicker">{{ __('ui.support.before_reporting') }}</p>
            <h2 id="support-diagnostics-heading" class="text-2xl font-semibold text-white">{{ __('ui.support.diagnostics') }}</h2>
            <p class="max-w-4xl leading-7 text-silver">{{ __('ui.support.diagnostics_help') }}</p>
            <pre class="overflow-x-auto rounded-xl border border-white/10 bg-black/25 p-4 text-sm text-silver"><code>./docker/compose ps
./docker/compose logs --tail=100 app scheduler nginx redis</code></pre>
            <p class="rounded-xl border border-gold/25 bg-gold/5 p-4 text-sm leading-6 text-gold">{{ __('ui.support.privacy') }}</p>
        </section>

        <section class="panel-card grid gap-4 p-6 sm:p-8" aria-labelledby="support-donate-heading">
            <p class="kicker">{{ __('ui.support.development') }}</p>
            <h2 id="support-donate-heading" class="text-2xl font-semibold text-white">{{ __('ui.support.donate') }}</h2>
            <p class="max-w-3xl leading-7 text-silver">{{ __('ui.support.donate_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-3">
                <a class="button button-ghost" href="https://buymeacoffee.com/interweb.bg" target="_blank" rel="noopener noreferrer">Buy Me a Coffee ↗</a>
                <a class="button button-ghost" href="https://ko-fi.com/interwebltd" target="_blank" rel="noopener noreferrer">Ko-fi ↗</a>
                <a class="button button-ghost" href="https://paypal.me/interwebltd" target="_blank" rel="noopener noreferrer">PayPal ↗</a>
            </div>
            <p class="text-sm text-steel">{{ __('ui.support.donate_notice') }}</p>
        </section>
    </div>
@endsection
