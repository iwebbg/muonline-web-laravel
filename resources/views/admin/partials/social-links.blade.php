<section class="panel-card p-5 sm:p-7" id="social-links">
    <h2 class="text-xl font-semibold text-white">{{ __('ui.social.title') }}</h2>
    <p class="mt-3 text-sm text-silver">{{ __('ui.social.help') }}</p>
    <div class="mt-5 grid gap-4">
        @foreach ($socialLinks as $socialLink)
            <div class="rounded-xl border border-white/10 p-4">
                <form method="POST" action="{{ route('admin.social-links.update', $socialLink) }}" class="grid items-end gap-3 md:grid-cols-2 xl:grid-cols-[10rem_1fr_2fr_6rem_auto]">
                    @csrf
                    @method('PUT')
                    @include('admin.partials.social-link-fields', ['link' => $socialLink])
                    <button class="button button-ghost" type="submit">{{ __('ui.social.save') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.social-links.destroy', $socialLink) }}" class="mt-3" x-data="{ confirming: false }">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="text-sm text-red-200 hover:text-white" x-show="!confirming" x-on:click="confirming = true">{{ __('ui.social.remove') }}</button>
                    <div x-cloak x-show="confirming" class="flex items-center gap-3">
                        <span class="text-sm text-silver">{{ __('ui.social.confirm_remove') }}</span>
                        <button type="submit" class="button button-ghost">{{ __('ui.social.remove') }}</button>
                        <button type="button" class="button button-ghost" x-on:click="confirming = false">{{ __('ui.social.cancel') }}</button>
                    </div>
                </form>
            </div>
        @endforeach
        <form method="POST" action="{{ route('admin.social-links.store') }}" class="grid items-end gap-3 rounded-xl border border-gold/20 p-4 md:grid-cols-2 xl:grid-cols-[10rem_1fr_2fr_6rem_auto]">
            @csrf
            @include('admin.partials.social-link-fields', ['link' => null])
            <button class="button button-primary" type="submit">{{ __('ui.social.add') }}</button>
        </form>
    </div>
</section>
