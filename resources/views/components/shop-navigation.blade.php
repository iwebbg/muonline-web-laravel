@props(['active'])

@inject('moduleManager', 'App\Modules\ModuleManager')
@inject('versionProfile', 'App\Game\Versions\Contracts\GameVersionProfile')

<nav {{ $attributes->class('flex flex-wrap gap-3') }} aria-label="{{ __('ui.navigation.shop') }}">
    @if ($moduleManager->enabled(\App\Modules\ServerModule::WebShop))
        <a href="{{ route('shop.items.index') }}" class="button {{ $active === 'items' ? 'button-primary' : 'button-ghost' }}">
            {{ __('ui.shop.items_heading') }}
        </a>
    @endif
    @if ($moduleManager->enabled(\App\Modules\ServerModule::MysteryBoxes))
        <a href="{{ route('shop.mystery-boxes.index') }}" class="button {{ $active === 'mystery-boxes' ? 'button-primary' : 'button-ghost' }}">
            {{ __('ui.shop.boxes_heading') }}
        </a>
    @endif
    @if ($moduleManager->enabled(\App\Modules\ServerModule::WebShop) && $versionProfile->capabilities()->itemGenerator)
        <a href="{{ route('account.item-generator.index') }}" class="button {{ $active === 'item-generator' ? 'button-primary' : 'button-ghost' }}">
            {{ __('ui.shop.generator_heading') }}
        </a>
    @endif
    @if ($moduleManager->enabled(\App\Modules\ServerModule::Rewards))
        <a href="{{ route('account.rewards.index') }}" class="button {{ $active === 'rewards' ? 'button-primary' : 'button-ghost' }}">
            {{ __('ui.rewards.heading') }}
        </a>
    @endif
</nav>
