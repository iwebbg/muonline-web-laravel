<?php

namespace Tests\Feature;

use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\Version1_0M\ItemCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemCodec1_0M;
use App\Game\Versions\Version1_0M\ItemGeneratorCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemGrantRules1_0M;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Verifies item services switch as one atomic game-version bundle.
 */
final class GameVersionItemServiceBindingTest extends TestCase
{
    /**
     * Confirms a 1.0M process cannot resolve a 97d item service by accident.
     */
    public function test_darksteam_1_0m_selects_only_its_item_services(): void
    {
        config()->set('game.profile.key', 'darksteam_1_0m');
        $this->app->forgetInstance(GameVersionProfile::class);

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(ItemCatalog1_0M::class, $this->app->make(ItemCatalog::class));
        $this->assertInstanceOf(ItemCodec1_0M::class, $this->app->make(ItemCodec::class));
        $this->assertInstanceOf(ItemGrantRules1_0M::class, $this->app->make(ItemGrantRules::class));
        $this->assertInstanceOf(
            ItemGeneratorCatalog1_0M::class,
            $this->app->make(ItemGeneratorCatalog::class),
        );
    }
}
