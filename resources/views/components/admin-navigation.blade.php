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
            <a href="{{ route('admin.dashboard') }}#character-rules" class="admin-nav-link {{ $active === 'character-rules' ? 'admin-nav-link-active' : '' }}">
                <span>↻</span>{{ __('ui.admin.character_rules') }}
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
        @if (auth('admin')->user()?->role === \App\Admin\Authorization\AdministratorRole::Administrator)
            <a href="{{ route('admin.game-masters.index') }}" class="admin-nav-link {{ $active === 'game-masters' ? 'admin-nav-link-active' : '' }}">
                <span>♜</span>{{ __('ui.admin.game_masters') }}
            </a>
        @endif
        @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageEconomy))
            <a href="{{ route('admin.wallet.index') }}" class="admin-nav-link {{ $active === 'economy' ? 'admin-nav-link-active' : '' }}">
                <span>◈</span>{{ __('ui.admin.economy') }}
            </a>
            <a href="{{ route('admin.vip-plans.index') }}" class="admin-nav-link {{ $active === 'vip-plans' ? 'admin-nav-link-active' : '' }}">
                <span>◆</span>{{ __('ui.admin.vip_plans_nav') }}
            </a>
        @else
            <span class="admin-nav-link opacity-45" aria-disabled="true"><span>◈</span>{{ __('ui.admin.economy') }}</span>
        @endif
        <span class="admin-nav-link opacity-45" aria-disabled="true"><span>◇</span>{{ __('ui.admin.modules') }}</span>
        @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ManageContent))
            <a href="{{ route('admin.news-posts.index') }}" class="admin-nav-link {{ $active === 'content' ? 'admin-nav-link-active' : '' }}">
                <span>☷</span>{{ __('ui.admin.content') }}
            </a>
            <a href="{{ route('admin.downloads.index') }}" class="admin-nav-link {{ $active === 'downloads' ? 'admin-nav-link-active' : '' }}">
                <span>⇩</span>{{ __('ui.admin.downloads') }}
            </a>
        @else
            <span class="admin-nav-link opacity-45" aria-disabled="true"><span>☷</span>{{ __('ui.admin.content') }}</span>
        @endif
        @if (auth('admin')->user()?->hasPermission(\App\Admin\Authorization\AdministratorPermission::ViewAuditLog))
            <a href="{{ route('admin.audit-log') }}" class="admin-nav-link {{ $active === 'audit-log' ? 'admin-nav-link-active' : '' }}">
                <span>◎</span>{{ __('ui.admin.audit_log') }}
            </a>
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
