@extends('layouts.app')

@section('title', __('ui.admin.vip_plans_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.vip_plans_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.vip_plans_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="vip-plans" />

            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.vip_plan_new') }}</p>
                    <form method="POST" action="{{ route('admin.vip-plans.store') }}" class="mt-5 grid gap-5">
                        @csrf
                        <div class="grid gap-5 md:grid-cols-3">
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_slug') }}</span>
                                <input type="text" name="slug" value="{{ old('slug') }}" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_name_en') }}</span>
                                <input type="text" name="name_en" value="{{ old('name_en') }}" maxlength="120" required>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_name_bg') }}</span>
                                <input type="text" name="name_bg" value="{{ old('name_bg') }}" maxlength="120">
                            </label>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_description_en') }}</span>
                                <textarea name="description_en" rows="3" maxlength="2000">{{ old('description_en') }}</textarea>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_description_bg') }}</span>
                                <textarea name="description_bg" rows="3" maxlength="2000">{{ old('description_bg') }}</textarea>
                            </label>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_days') }}</span>
                                <input type="number" name="duration_days" value="{{ old('duration_days', 30) }}" min="1" max="3650" required>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_price') }}</span>
                                <input type="number" name="price_credits" value="{{ old('price_credits', 1000) }}" min="1" max="1000000000" required>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.vip_plan_order') }}</span>
                                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="10000" required>
                            </label>
                        </div>
                        <label class="flex items-center gap-3 text-sm text-silver">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', true))>
                            {{ __('ui.admin.vip_plan_enabled') }}
                        </label>
                        <div class="flex justify-end">
                            <button type="submit" class="button button-primary">{{ __('ui.admin.vip_plan_create') }}</button>
                        </div>
                    </form>
                </section>

                <section class="grid gap-4">
                    @forelse ($plans as $plan)
                        <article class="panel-card p-5 sm:p-7" x-data="{ editing: false }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-xl font-semibold text-white">{{ $plan->name_en }}</h2>
                                        <span @class([
                                            'rounded-full px-3 py-1 text-xs font-semibold',
                                            'bg-emerald-300/10 text-emerald-200' => $plan->enabled,
                                            'bg-white/5 text-steel' => ! $plan->enabled,
                                        ])>{{ $plan->enabled ? __('ui.admin.vip_plan_active') : __('ui.admin.vip_plan_inactive') }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-silver">{{ $plan->duration_days }} {{ __('ui.admin.vip_plan_days_short') }} · {{ number_format($plan->price_credits) }} Credits</p>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" class="button button-ghost" @click="editing = ! editing">{{ __('ui.admin.vip_plan_edit') }}</button>
                                    <form method="POST" action="{{ route('admin.vip-plans.destroy', $plan) }}" onsubmit="return confirm(@js(__('ui.admin.vip_plan_delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button button-ghost text-red-200">{{ __('ui.admin.vip_plan_delete') }}</button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.vip-plans.update', $plan) }}" class="mt-6 grid gap-5 border-t border-white/8 pt-6" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                <div class="grid gap-5 md:grid-cols-3">
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_slug') }}</span><input name="slug" value="{{ $plan->slug }}" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_name_en') }}</span><input name="name_en" value="{{ $plan->name_en }}" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_name_bg') }}</span><input name="name_bg" value="{{ $plan->name_bg }}"></label>
                                </div>
                                <div class="grid gap-5 md:grid-cols-2">
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_description_en') }}</span><textarea name="description_en" rows="3">{{ $plan->description_en }}</textarea></label>
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_description_bg') }}</span><textarea name="description_bg" rows="3">{{ $plan->description_bg }}</textarea></label>
                                </div>
                                <div class="grid gap-5 sm:grid-cols-3">
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_days') }}</span><input type="number" name="duration_days" value="{{ $plan->duration_days }}" min="1" max="3650" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_price') }}</span><input type="number" name="price_credits" value="{{ $plan->price_credits }}" min="1" max="1000000000" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.vip_plan_order') }}</span><input type="number" name="sort_order" value="{{ $plan->sort_order }}" min="0" max="10000" required></label>
                                </div>
                                <label class="flex items-center gap-3 text-sm text-silver"><input type="checkbox" name="enabled" value="1" @checked($plan->enabled)>{{ __('ui.admin.vip_plan_enabled') }}</label>
                                <div class="flex justify-end"><button type="submit" class="button button-primary">{{ __('ui.admin.vip_plan_save') }}</button></div>
                            </form>
                        </article>
                    @empty
                        <div class="panel-card p-8 text-center text-steel">{{ __('ui.admin.vip_plan_empty') }}</div>
                    @endforelse
                </section>

                <section class="panel-card overflow-hidden">
                    <div class="border-b border-white/8 p-5 sm:p-6">
                        <p class="kicker">{{ __('ui.admin.vip_sync_heading') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr>
                                    <th class="px-5 py-4">{{ __('ui.admin.wallet_account_id') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.account.vip_character') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.vip_sync_status') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.account.vip_expires') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @forelse ($recentEntitlements as $entitlement)
                                    <tr>
                                        <td class="px-5 py-4 text-white">{{ $entitlement->account_id }}</td>
                                        <td class="px-5 py-4 text-white">{{ $entitlement->character_name }}</td>
                                        <td class="px-5 py-4 text-silver">{{ __('ui.account.vip_sync_'.$entitlement->sync_status->value) }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $entitlement->expires_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.vip_sync_empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
@endsection
