@extends('layouts.admin')

@section('title', __('ui.admin.vote_history_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.vote_history_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.vote_history_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="vote-history" />

            <main class="min-w-0">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <section class="panel-card overflow-hidden">
                    <div class="border-b border-white/8 p-5 sm:p-6">
                        <p class="kicker">{{ __('ui.admin.vote_claims_heading') }}</p>
                        <form method="GET" action="{{ route('admin.vote-claims.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(16rem,1fr)_12rem_10rem_10rem_auto]">
                            <label class="field-group">
                                <span>{{ __('ui.admin.vote_claim_search') }}</span>
                                <input type="search" name="search" value="{{ $claimFilters['search'] }}" maxlength="120" placeholder="{{ __('ui.admin.vote_claim_search_placeholder') }}">
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vote_claim_status') }}</span>
                                <select name="status">
                                    <option value="">{{ __('ui.admin.vote_claim_status_all') }}</option>
                                    <option value="pending" @selected($claimFilters['status'] === 'pending')>{{ __('ui.votes.statuses.pending') }}</option>
                                    <option value="rewarded" @selected($claimFilters['status'] === 'rewarded')>{{ __('ui.votes.statuses.rewarded') }}</option>
                                    <option value="rejected" @selected($claimFilters['status'] === 'rejected')>{{ __('ui.votes.statuses.rejected') }}</option>
                                </select>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.date_from') }}</span>
                                <input type="date" name="date_from" value="{{ $claimFilters['dateFrom'] }}">
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.date_to') }}</span>
                                <input type="date" name="date_to" value="{{ $claimFilters['dateTo'] }}">
                            </label>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="button button-primary">{{ __('ui.admin.vote_claim_filter') }}</button>
                                <a href="{{ route('admin.vote-claims.index') }}" class="button button-ghost">{{ __('ui.admin.vote_claim_clear') }}</a>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr>
                                    <th class="px-5 py-4">{{ __('ui.admin.vote_claim_account') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.vote_claim_site') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.vote_claim_reward') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.vote_claim_status') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.vote_claim_time') }}</th>
                                    <th class="px-5 py-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @forelse ($claims as $claim)
                                    <tr x-data="{ rejecting: false }">
                                        <td class="px-5 py-4 text-white">{{ $claim->account_id }}</td>
                                        <td class="px-5 py-4 text-white">{{ $claim->site->name_en }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-silver">{{ number_format($claim->reward_credits) }} C · {{ number_format($claim->reward_zen) }} Zen</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                'border-amber-300/25 bg-amber-300/8 text-amber-100' => $claim->status === \App\VoteRewards\VoteClaimStatus::Pending,
                                                'border-emerald-300/25 bg-emerald-300/8 text-emerald-100' => $claim->status === \App\VoteRewards\VoteClaimStatus::Rewarded,
                                                'border-red-300/25 bg-red-300/8 text-red-100' => $claim->status === \App\VoteRewards\VoteClaimStatus::Rejected,
                                                'border-white/10 bg-white/5 text-steel' => $claim->status === \App\VoteRewards\VoteClaimStatus::Expired,
                                            ])>
                                                {{ __('ui.votes.statuses.'.$claim->status->value) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $claim->initiated_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-5 py-4">
                                            @if ($claim->status === \App\VoteRewards\VoteClaimStatus::Pending)
                                                <div class="flex min-w-64 flex-wrap justify-end gap-2">
                                                    <form method="POST" action="{{ route('admin.vote-claims.review', $claim) }}">
                                                        @csrf
                                                        <button type="submit" name="decision" value="approve" class="button button-primary">{{ __('ui.admin.vote_claim_approve') }}</button>
                                                    </form>
                                                    <button type="button" class="button button-ghost text-red-200" x-on:click="rejecting = true">{{ __('ui.admin.vote_claim_reject') }}</button>
                                                </div>

                                                <template x-teleport="body">
                                                    <div x-cloak x-show="rejecting" class="fixed inset-0 z-[120] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="rejecting = false" role="dialog" aria-modal="true">
                                                        <form method="POST" action="{{ route('admin.vote-claims.review', $claim) }}" class="panel-card w-full max-w-lg p-6 sm:p-8">
                                                        @csrf
                                                        <input type="hidden" name="decision" value="reject">
                                                        <p class="kicker">{{ __('ui.admin.vote_claim_reject_heading') }}</p>
                                                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ $claim->account_id }} · {{ $claim->site->name_en }}</h2>
                                                        <label class="field-group mt-6">
                                                            <span>{{ __('ui.admin.vote_claim_reason') }}</span>
                                                            <textarea name="reason" rows="4" maxlength="255" required></textarea>
                                                        </label>
                                                        <p class="mt-2 text-xs text-steel">{{ __('ui.admin.vote_claim_reason_help') }}</p>
                                                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                                            <button type="button" class="button button-ghost" x-on:click="rejecting = false">{{ __('ui.admin.news_cancel') }}</button>
                                                            <button type="submit" class="button button-primary">{{ __('ui.admin.vote_claim_confirm_reject') }}</button>
                                                        </div>
                                                        </form>
                                                    </div>
                                                </template>
                                            @elseif ($claim->rejection_reason)
                                                <span class="block max-w-64 break-words text-xs text-steel">
                                                    <strong class="text-silver">{{ __('ui.admin.vote_claim_internal_note') }}:</strong>
                                                    {{ $claim->rejection_reason }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.vote_claims_empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($claims->hasPages())
                        <div class="border-t border-white/8 p-5">{{ $claims->links() }}</div>
                    @endif
                </section>
            </main>
        </div>
    </div>
@endsection
