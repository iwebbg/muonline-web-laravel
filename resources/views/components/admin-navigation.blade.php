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
        <span class="admin-nav-link opacity-45" aria-disabled="true"><span>◈</span>{{ __('ui.admin.economy') }}</span>
        <span class="admin-nav-link opacity-45" aria-disabled="true"><span>◇</span>{{ __('ui.admin.modules') }}</span>
        <span class="admin-nav-link opacity-45" aria-disabled="true"><span>☷</span>{{ __('ui.admin.content') }}</span>
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
