@extends('layouts.admin')

@section('title', __('ui.admin.recovery_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.recovery_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.recovery_intro') }}</p>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('recovery'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                {{ $errors->first('recovery') }}
            </div>
        @endif

        @if (! $gameWritesEnabled)
            <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">
                {{ __('ui.admin.recovery_writes_disabled') }}
            </div>
        @endif

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="economy-recovery" />

            <main class="grid min-w-0 content-start gap-8">
                <section>
                    <p class="kicker">{{ __('ui.admin.recovery_item_custody') }}</p>
                    <div class="mt-4 grid gap-4">
                        @forelse ($storageItems as $item)
                            <article class="panel-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-white">{{ $item->account_id }} · {{ $item->source_character_name }}</p>
                                    <p class="mt-1 text-sm text-steel">
                                        {{ $item->item_snapshot['name'] ?? __('ui.market.unknown_item') }} · {{ $item->status->value }} · {{ __('ui.admin.recovery_attempts', ['count' => $item->sync_attempts]) }}
                                    </p>
                                    @if ($item->last_sync_error)
                                        <p class="mt-1 text-sm text-red-200">{{ $item->last_sync_error }}</p>
                                    @endif
                                </div>
                                @if (in_array($item->status, [\App\WebStorage\WebStorageItemStatus::Storing, \App\WebStorage\WebStorageItemStatus::WithdrawalPending], true))
                                    <form method="POST" action="{{ route('admin.economy-recovery.storage.retry', $item) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>
                                            {{ __('ui.admin.recovery_retry') }}
                                        </button>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="panel-card p-8 text-center text-steel">{{ __('ui.admin.recovery_no_items') }}</div>
                        @endforelse
                    </div>
                </section>

                <section>
                    <p class="kicker">{{ __('ui.admin.recovery_zen') }}</p>
                    <div class="mt-4 grid gap-4">
                        @forelse ($zenOperations as $operation)
                            <article class="panel-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-white">{{ $operation->account_id }} · {{ $operation->character_name }}</p>
                                    <p class="mt-1 text-sm text-steel">
                                        {{ $operation->type->value }} · {{ number_format($operation->amount) }} Zen · {{ $operation->status->value }} · {{ __('ui.admin.recovery_attempts', ['count' => $operation->sync_attempts]) }}
                                    </p>
                                    @if ($operation->last_sync_error)
                                        <p class="mt-1 text-sm text-red-200">{{ $operation->last_sync_error }}</p>
                                    @endif
                                </div>
                                @if ($operation->status === \App\ZenBank\ZenBankOperationStatus::Pending)
                                    <form method="POST" action="{{ route('admin.economy-recovery.zen.retry', $operation) }}">
                                        @csrf
                                        <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>
                                            {{ __('ui.admin.recovery_retry') }}
                                        </button>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="panel-card p-8 text-center text-steel">{{ __('ui.admin.recovery_no_zen') }}</div>
                        @endforelse
                    </div>
                </section>

                <section>
                    <p class="kicker">{{ __('ui.admin.recovery_recent_actions') }}</p>
                    <div class="panel-card mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-white/8 text-steel">
                                <tr>
                                    <th class="p-4">{{ __('ui.admin.recovery_subject') }}</th>
                                    <th class="p-4">{{ __('ui.admin.recovery_transition') }}</th>
                                    <th class="p-4">{{ __('ui.admin.recovery_outcome') }}</th>
                                    <th class="p-4">{{ __('ui.admin.signed_in_as') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentActions as $action)
                                    <tr class="border-b border-white/5 last:border-0">
                                        <td class="p-4 text-white">{{ $action->subject_type }} · {{ $action->account_id }}</td>
                                        <td class="p-4 text-steel">{{ $action->before_status }} → {{ $action->after_status }}</td>
                                        <td class="p-4 text-steel">{{ $action->outcome }}</td>
                                        <td class="p-4 text-steel">{{ $action->administrator?->name ?? 'system' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="p-8 text-center text-steel">{{ __('ui.admin.recovery_no_actions') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
@endsection
