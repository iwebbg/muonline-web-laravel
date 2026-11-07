<?php

namespace App\Game\Versions\Contracts;

use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Stats\CharacterStats;

/**
 * Defines all class, stat and persistence adapters owned by one game version.
 */
interface GameVersionProfile
{
    /**
     * Returns optional persistence features implemented by this game schema.
     */
    public function capabilities(): GameVersionCapabilities;

    /**
     * Returns the stable configuration key used to select this profile.
     */
    public function key(): string;

    /**
     * Returns every playable class evolution supported by the profile.
     *
     * @return list<CharacterClassDefinition>
     */
    public function classes(): array;

    /**
     * Resolves one legacy class code or returns null when unsupported.
     */
    public function classForCode(int $classCode): ?CharacterClassDefinition;

    /**
     * Resolves one public class-filter key or returns null when unsupported.
     */
    public function classForKey(string $classKey): ?CharacterClassDefinition;

    /**
     * Returns the character-family filters supported by this game version.
     *
     * @return list<ShopClassScopeDefinition>
     */
    public function shopClassScopes(): array;

    /**
     * Returns every attribute known by this version.
     *
     * @return list<CharacterStatDefinition>
     */
    public function stats(): array;

    /**
     * Returns the attributes available to one class evolution.
     *
     * @return list<CharacterStatDefinition>
     */
    public function statsForClass(int $classCode): array;

    /**
     * Returns the level-one attributes for one class family.
     */
    public function baseStatsForClass(int $classCode): CharacterStats;

    /**
     * Returns contract-to-adapter bindings owned by this version.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array;
}
