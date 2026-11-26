<label class="field-group">
    <span>{{ __('ui.social.platform') }}</span>
    <select name="type" required>
        @foreach (\App\Website\SocialPlatform::cases() as $platform)
            <option value="{{ $platform->value }}" @selected($link?->type === $platform)>{{ $platform->label() }}</option>
        @endforeach
    </select>
</label>
<label class="field-group">
    <span>{{ __('ui.social.label') }}</span>
    <input name="label" value="{{ $link?->label }}" maxlength="80">
</label>
<label class="field-group">
    <span>URL</span>
    <input type="url" name="url" value="{{ $link?->url }}" required maxlength="2048" placeholder="https://">
</label>
<label class="field-group">
    <span>{{ __('ui.social.order') }}</span>
    <input type="number" name="sort_order" value="{{ $link?->sort_order ?? 0 }}" min="0" max="10000" required>
</label>
