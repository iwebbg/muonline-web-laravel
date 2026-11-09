@props([
    'active',
    'resetRevision' => null,
])

<aside {{ $attributes->class('panel-card h-fit p-3') }}>
    <p class="px-3 pb-3 pt-2 text-xs font-semibold uppercase tracking-[0.2em] text-steel">
        {{ __('ui.admin.control_areas') }}
    </p>
    <nav class="grid gap-1" aria-label="{{ __('ui.admin.sections_label') }}">
        @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageSettings))
            <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ $active === 'overview' ? 'admin-nav-link-active' : '' }}">
                <span>⌁</span>{{ __('ui.admin.overview') }}
            </a>
            <div class="mt-2 border-t border-white/8 pt-2">
                <p class="flex items-center gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                    <span>↻</span>{{ __('ui.admin.character_rules') }}
                </p>
                <div class="grid gap-1 pl-4">
                    @foreach ([
                        'reset' => 'reset_profile',
                        'grand-reset' => 'grand_reset_profile',
                        'stat-reset' => 'stat_reset_profile',
                        'pk-clear' => 'pk_clear_profile',
                        'move-to-town' => 'move_to_town_profile',
                    ] as $profile => $label)
                        <a href="{{ route('admin.character-rules.edit', ['profile' => $profile]) }}" class="admin-nav-link {{ $active === 'character-rules-'.$profile ? 'admin-nav-link-active' : '' }}">
                            <span>·</span>{{ __('ui.admin.'.$label) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        @if (
            auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageSettings)
            || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ModerateAccounts)
            || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ModerateCharacters)
            || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::GrantItems)
            || auth('admin')->user()?->role === \App\Admin\Authorization\AdministratorRole::Administrator
        )
            <div class="mt-2 border-t border-white/8 pt-2">
                <p class="flex items-center gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                    <span>⚙</span>{{ __('ui.admin.game_operations') }}
                </p>
                <div class="grid gap-1 pl-4">
                    @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageSettings))
                        <a href="{{ route('admin.event-schedules.index') }}" class="admin-nav-link {{ $active === 'event-schedules' ? 'admin-nav-link-active' : '' }}">
                            <span>◷</span>{{ __('ui.admin.event_schedules_nav') }}
                        </a>
                    @endif
                    @if (
                        auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ModerateAccounts)
                        || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ModerateCharacters)
                    )
                        <a href="{{ route('admin.moderation') }}" class="admin-nav-link {{ $active === 'moderation' ? 'admin-nav-link-active' : '' }}">
                            <span>⚑</span>{{ __('ui.admin.moderation') }}
                        </a>
                    @endif
                    @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::GrantItems))
                        <a href="{{ route('admin.item-grants.index') }}" class="admin-nav-link {{ $active === 'item-grants' ? 'admin-nav-link-active' : '' }}">
                            <span>✧</span>{{ __('ui.admin.item_grants_nav') }}
                        </a>
                    @endif
                    @if (auth('admin')->user()?->role === \App\Admin\Authorization\AdministratorRole::Administrator)
                        <a href="{{ route('admin.game-masters.index') }}" class="admin-nav-link {{ $active === 'game-masters' ? 'admin-nav-link-active' : '' }}">
                            <span>♜</span>{{ __('ui.admin.game_masters') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif
        @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageEconomy))
            <div class="mt-2 border-t border-white/8 pt-2">
                <p class="flex items-center gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                    <span>◈</span>{{ __('ui.admin.economy') }}
                </p>
                <div class="grid gap-1 pl-4">
                    <a href="{{ route('admin.economy-settings.edit') }}" class="admin-nav-link {{ $active === 'economy-settings' ? 'admin-nav-link-active' : '' }}">
                        <span>⚙</span>{{ __('ui.admin.economy_settings_nav') }}
                    </a>
                    <a href="{{ route('admin.wallet.index') }}" class="admin-nav-link {{ $active === 'economy' ? 'admin-nav-link-active' : '' }}">
                        <span>¤</span>{{ __('ui.admin.wallet_nav') }}
                    </a>
                    <a href="{{ route('admin.credit-packages.index') }}" class="admin-nav-link {{ $active === 'credit-packages' ? 'admin-nav-link-active' : '' }}">
                        <span>＋</span>{{ __('ui.admin.credit_packages_nav') }}
                    </a>
                    <a href="{{ route('admin.credit-payments.index') }}" class="admin-nav-link {{ $active === 'credit-payments' ? 'admin-nav-link-active' : '' }}">
                        <span>◷</span>{{ __('ui.admin.credit_purchase_history_nav') }}
                    </a>
                    <a href="{{ route('admin.vip-plans.index') }}" class="admin-nav-link {{ $active === 'vip-plans' ? 'admin-nav-link-active' : '' }}">
                        <span>◆</span>{{ __('ui.admin.vip_plans_nav') }}
                    </a>
                    <a href="{{ route('admin.market.index') }}" class="admin-nav-link {{ $active === 'market' ? 'admin-nav-link-active' : '' }}">
                        <span>◇</span>{{ __('ui.admin.market_nav') }}
                    </a>
                    <a href="{{ route('admin.shop-offers.index') }}" class="admin-nav-link {{ $active === 'shop-offers' ? 'admin-nav-link-active' : '' }}">
                        <span>◈</span>{{ __('ui.admin.shop_offers_nav') }}
                    </a>
                    <a href="{{ route('admin.economy-recovery.index') }}" class="admin-nav-link {{ $active === 'economy-recovery' ? 'admin-nav-link-active' : '' }}">
                        <span>⟳</span>{{ __('ui.admin.recovery_nav') }}
                    </a>
                </div>
            </div>
            <div class="mt-2 border-t border-white/8 pt-2">
                <p class="flex items-center gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                    <span>✦</span>{{ __('ui.admin.vote_rewards_nav') }}
                </p>
                <div class="grid gap-1 pl-4">
                    <a href="{{ route('admin.vote-sites.index') }}" class="admin-nav-link {{ $active === 'vote-links' ? 'admin-nav-link-active' : '' }}">
                        <span>↗</span>{{ __('ui.admin.vote_links_nav') }}
                    </a>
                    <a href="{{ route('admin.vote-claims.index') }}" class="admin-nav-link {{ $active === 'vote-history' ? 'admin-nav-link-active' : '' }}">
                        <span>◷</span>{{ __('ui.admin.vote_history_nav') }}
                    </a>
                </div>
            </div>
        @endif
        @if (
            auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageSettings)
            || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageContent)
            || auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ViewAuditLog)
        )
            <div class="mt-2 border-t border-white/8 pt-2">
                <p class="flex items-center gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                    <span>▦</span>{{ __('ui.admin.web_control') }}
                </p>
                <div class="grid gap-1 pl-4">
                    @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageSettings))
                        <a href="{{ route('admin.game-servers.index') }}" class="admin-nav-link {{ $active === 'game-servers' ? 'admin-nav-link-active' : '' }}">
                            <span>◉</span>{{ __('ui.admin.game_servers_nav') }}
                        </a>
                        <a href="{{ route('admin.website-settings.edit') }}" class="admin-nav-link {{ $active === 'website-settings' ? 'admin-nav-link-active' : '' }}">
                            <span>◆</span>{{ __('ui.admin.website_settings_nav') }}
                        </a>
                        <a href="{{ route('admin.modules.index') }}" class="admin-nav-link {{ $active === 'modules' ? 'admin-nav-link-active' : '' }}">
                            <span>◫</span>{{ __('ui.admin.modules') }}
                        </a>
                    @endif
                    @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageContent))
                        <a href="{{ route('admin.news-posts.index') }}" class="admin-nav-link {{ $active === 'content' ? 'admin-nav-link-active' : '' }}">
                            <span>☷</span>{{ __('ui.admin.content') }}
                        </a>
                        <a href="{{ route('admin.downloads.index') }}" class="admin-nav-link {{ $active === 'downloads' ? 'admin-nav-link-active' : '' }}">
                            <span>⇩</span>{{ __('ui.admin.downloads') }}
                        </a>
                    @endif
                    @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ViewAuditLog))
                        <a href="{{ route('admin.audit-log') }}" class="admin-nav-link {{ $active === 'audit-log' ? 'admin-nav-link-active' : '' }}">
                            <span>◎</span>{{ __('ui.admin.audit_log') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </nav>

    @if ($resetRevision !== null)
        <div class="mt-4 rounded-xl border border-white/8 bg-black/20 p-3">
            <p class="text-xs uppercase tracking-[0.18em] text-steel">{{ __('ui.admin.active_profile') }}</p>
            <p class="mt-2 text-sm font-semibold text-white">{{ __('ui.admin.medium_reset') }}</p>
            <p class="mt-1 text-xs text-steel">
                {{ __('ui.admin.current_revision', ['revision' => '']) }}
                <span x-text="revision">{{ $resetRevision }}</span>
            </p>
        </div>
    @endif
</aside>
