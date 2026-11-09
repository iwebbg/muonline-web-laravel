<?php

namespace Tests\Unit;

use App\CharacterServices\CharacterClassChangeResolver;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies class-family mapping without granting evolution upgrades.
 */
final class CharacterClassChangeResolverTest extends TestCase
{
    /**
     * Confirms both profiles preserve the highest already-earned evolution tier.
     *
     * @param  class-string<GameVersionProfile>  $profileClass
     */
    #[DataProvider('tierMappings')]
    public function test_resolves_target_family_at_the_current_supported_tier(
        string $profileClass,
        int $currentClassCode,
        string $targetFamily,
        int $expectedClassCode,
    ): void {
        $resolver = new CharacterClassChangeResolver(new $profileClass);

        $resolved = $resolver->resolve($currentClassCode, $targetFamily);

        $this->assertSame($expectedClassCode, $resolved->code);
    }

    /**
     * Returns representative base, evolved and 1.0M-only mappings.
     *
     * @return iterable<string, array{class-string<GameVersionProfile>, int, string, int}>
     */
    public static function tierMappings(): iterable
    {
        yield '97d base remains base' => [Version97d99iProfile::class, 0, 'knight', 16];
        yield '97d evolved remains evolved' => [Version97d99iProfile::class, 17, 'elf', 33];
        yield '1.0M evolved to single-tier Dark Lord' => [Version1_0MProfile::class, 17, 'dark-lord', 64];
        yield '1.0M Dark Lord to base wizard' => [Version1_0MProfile::class, 64, 'wizard', 0];
    }
}
