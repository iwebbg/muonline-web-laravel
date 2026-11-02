<div class="grid gap-5 md:grid-cols-3">
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_slug') }}</span>
        <input type="text" name="slug" value="{{ old('slug', $site?->slug) }}" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_name_en') }}</span>
        <input type="text" name="name_en" value="{{ old('name_en', $site?->name_en) }}" maxlength="120" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_name_bg') }}</span>
        <input type="text" name="name_bg" value="{{ old('name_bg', $site?->name_bg) }}" maxlength="120">
    </label>
</div>
<div class="grid gap-5 md:grid-cols-2">
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_url') }}</span>
        <input type="url" name="url" value="{{ old('url', $site?->url) }}" maxlength="2048" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_image_url') }}</span>
        <input type="url" name="image_url" value="{{ old('image_url', $site?->image_url) }}" maxlength="2048">
    </label>
</div>
<div class="grid gap-5 sm:grid-cols-4">
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_cooldown') }}</span>
        <input type="number" name="cooldown_hours" value="{{ old('cooldown_hours', $site?->cooldown_hours ?? 12) }}" min="1" max="168" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_credits') }}</span>
        <input type="number" name="reward_credits" value="{{ old('reward_credits', $site?->reward_credits ?? 10) }}" min="0" max="1000000000" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_zen') }}</span>
        <input type="number" name="reward_zen" value="{{ old('reward_zen', $site?->reward_zen ?? 1000000) }}" min="0" max="2000000000" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.vote_site_order') }}</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $site?->sort_order ?? 0) }}" min="0" max="10000" required>
    </label>
</div>
<label class="flex items-center gap-3 text-sm text-silver">
    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $site?->enabled ?? true))>
    {{ __('ui.admin.vote_site_enabled') }}
</label>
