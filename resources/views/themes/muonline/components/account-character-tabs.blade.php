<div {{ $attributes->class(['segmented-control w-full overflow-x-auto sm:w-fit']) }} role="tablist" aria-label="{{ __('ui.account.character_tools') }}">
    @php
        $sections = ['overview' => __('ui.account.overview'), 'inventory' => __('ui.account.inventory'), 'stats' => __('ui.account.allocate_stats'), 'stat-reset' => __('ui.account.stat_reset'), 'pk-clear' => __('ui.account.pk_clear'), 'move-to-town' => __('ui.account.move_to_town'), 'reset' => __('ui.account.reset'), 'grand-reset' => __('ui.account.grand_reset')];

        if ($characterServicesEnabled) {
            $sections['services'] = __('ui.account.character_services');
        }
    @endphp
    @foreach ($sections as $section => $label)
        <button
            type="button"
            role="tab"
            :aria-selected="activeSection === '{{ $section }}'"
            :class="activeSection === '{{ $section }}' && 'segmented-control-active'"
            @click="selectSection('{{ $section }}')"
        >{{ $label }}</button>
    @endforeach
</div>
