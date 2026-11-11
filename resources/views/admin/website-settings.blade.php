@extends('layouts.admin')

@section('title', __('ui.admin.website_title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.website_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.website_intro') }}</p>
        </div>

        <div class="mt-8">
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

                <form
                    method="POST"
                    action="{{ route('admin.website-settings.update') }}"
                    enctype="multipart/form-data"
                    class="grid gap-6"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="revision" value="{{ $settings->revision }}">

                    <section class="panel-card p-5 sm:p-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.website_branding') }}</p>
                                <h2 class="mt-2 text-xl font-semibold text-white">{{ __('ui.admin.website_header_identity') }}</h2>
                            </div>
                            <span class="resource-chip">{{ __('ui.admin.revision') }} {{ $settings->revision }}</span>
                        </div>

                        <div class="mt-6 grid items-start gap-5 lg:grid-cols-2">
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_brand_title') }}</span>
                                <input type="text" name="brand_title" value="{{ old('brand_title', $settings->brandTitle) }}" maxlength="80" placeholder="MUONLINE">
                                <small>{{ __('ui.admin.website_brand_title_help') }}</small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_brand_subtitle') }}</span>
                                <input type="text" name="brand_subtitle" value="{{ old('brand_subtitle', $settings->brandSubtitle) }}" maxlength="120" placeholder="{{ $currentGameServerVersion }}">
                            </label>
                            <label class="field-group lg:col-span-2">
                                <span>{{ __('ui.admin.website_logo') }}</span>
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                                <small>{{ __('ui.admin.website_logo_help') }}</small>
                            </label>
                            @if ($websiteLogoUrl !== null)
                                <div class="flex items-center gap-4 rounded-2xl border border-white/8 bg-black/20 p-4 lg:col-span-2">
                                    <img src="{{ $websiteLogoUrl }}" alt="" class="size-16 rounded-xl border border-gold/30 object-cover">
                                    <label class="flex items-center gap-3 text-sm text-silver">
                                        <input type="checkbox" name="remove_logo" value="1">
                                        {{ __('ui.admin.website_remove_logo') }}
                                    </label>
                                </div>
                            @endif
                            <label class="field-group lg:col-span-2">
                                <span>{{ __('ui.admin.website_copyright_holder') }}</span>
                                <input type="text" name="copyright_holder" value="{{ old('copyright_holder', $settings->copyrightHolder) }}" maxlength="160" placeholder="{{ $settings->pageTitleSuffix }}">
                                <small>{{ __('ui.admin.website_copyright_help') }}</small>
                            </label>
                            <label class="field-group lg:col-span-2">
                                <span>{{ __('ui.admin.website_theme') }}</span>
                                <select name="theme_slug" required>
                                    @foreach ($availableThemes as $theme)
                                        <option value="{{ $theme['slug'] }}" @selected(old('theme_slug', $settings->themeSlug) === $theme['slug'])>
                                            {{ $theme['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small>{{ __('ui.admin.website_theme_help') }}</small>
                            </label>
                        </div>
                    </section>

                    <section class="panel-card p-5 sm:p-7">
                        <div>
                            <p class="kicker">{{ __('ui.admin.website_seo') }}</p>
                            <h2 class="mt-2 text-xl font-semibold text-white">{{ __('ui.admin.website_search_metadata') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-steel">{{ __('ui.admin.website_seo_help') }}</p>
                        </div>

                        <div class="mt-6 grid items-start gap-5 lg:grid-cols-2">
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_home_title_en') }}</span>
                                <input type="text" name="home_title_en" value="{{ old('home_title_en', $settings->homeTitleEn) }}" maxlength="160">
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_home_title_bg') }}</span>
                                <input type="text" name="home_title_bg" value="{{ old('home_title_bg', $settings->homeTitleBg) }}" maxlength="160">
                            </label>
                            <label class="field-group lg:col-span-2">
                                <span>{{ __('ui.admin.website_title_suffix') }}</span>
                                <input type="text" name="page_title_suffix" value="{{ old('page_title_suffix', $settings->pageTitleSuffix) }}" maxlength="120">
                                <small>{{ __('ui.admin.website_title_suffix_help') }}</small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_description_en') }}</span>
                                <textarea name="meta_description_en" rows="4" maxlength="320">{{ old('meta_description_en', $settings->metaDescriptionEn) }}</textarea>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_description_bg') }}</span>
                                <textarea name="meta_description_bg" rows="4" maxlength="320">{{ old('meta_description_bg', $settings->metaDescriptionBg) }}</textarea>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_keywords_en') }}</span>
                                <textarea name="meta_keywords_en" rows="3" maxlength="500">{{ old('meta_keywords_en', $settings->metaKeywordsEn) }}</textarea>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.website_keywords_bg') }}</span>
                                <textarea name="meta_keywords_bg" rows="3" maxlength="500">{{ old('meta_keywords_bg', $settings->metaKeywordsBg) }}</textarea>
                            </label>
                            <label class="field-group lg:col-span-2">
                                <span>{{ __('ui.admin.website_og_image') }}</span>
                                <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp">
                                <small>{{ __('ui.admin.website_og_image_help') }}</small>
                            </label>
                            @if ($websiteOgImageUrl !== null)
                                <div class="grid gap-4 rounded-2xl border border-white/8 bg-black/20 p-4 lg:col-span-2 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-center">
                                    <img src="{{ $websiteOgImageUrl }}" alt="" class="aspect-[1.91/1] w-full rounded-xl border border-white/10 object-cover">
                                    <label class="flex items-center gap-3 text-sm text-silver">
                                        <input type="checkbox" name="remove_og_image" value="1">
                                        {{ __('ui.admin.website_remove_og_image') }}
                                    </label>
                                </div>
                            @endif
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button type="submit" class="button button-primary">{{ __('ui.admin.website_save') }}</button>
                    </div>
                </form>

                <x-admin-revision-history
                    :revisions="$recentRevisions"
                    :title="__('ui.admin.website_history')"
                    :empty-message="__('ui.admin.website_no_history')"
                    wide
                />
            </main>
        </div>
    </div>
@endsection
