<?php

namespace App\CharacterServices;

use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;

/**
 * Resolves a requested class family without granting a free evolution upgrade.
 */
final readonly class CharacterClassChangeResolver
{
    /**
     * Creates the resolver for the active version profile.
     */
    public function __construct(private GameVersionProfile $versionProfile) {}

    /**
     * Resolves the highest target evolution no greater than the current tier.
     */
    public function resolve(int $currentClassCode, string $targetFamily): CharacterClassDefinition
    {
        $currentClass = $this->versionProfile->classForCode($currentClassCode);

        if ($currentClass === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
        }

        if ($currentClass->family === $targetFamily) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::SameClassFamily);
        }

        $candidates = array_values(array_filter(
            $this->versionProfile->classes(),
            static fn (CharacterClassDefinition $definition): bool => $definition->family === $targetFamily
                && $definition->evolutionTier <= $currentClass->evolutionTier,
        ));

        usort(
            $candidates,
            static fn (CharacterClassDefinition $left, CharacterClassDefinition $right): int => $right->evolutionTier <=> $left->evolutionTier,
        );

        $targetClass = $candidates[0] ?? null;

        if (! $targetClass instanceof CharacterClassDefinition) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
        }

        return $targetClass;
    }

    /**
     * Returns one base class option for every family except the current family.
     *
     * @return list<CharacterClassDefinition>
     */
    public function optionsFor(int $currentClassCode): array
    {
        $currentClass = $this->versionProfile->classForCode($currentClassCode);

        if ($currentClass === null) {
            return [];
        }

        $families = [];

        foreach ($this->versionProfile->classes() as $definition) {
            if ($definition->family === $currentClass->family || isset($families[$definition->family])) {
                continue;
            }

            $families[$definition->family] = $this->resolve($currentClassCode, $definition->family);
        }

        return array_values($families);
    }
}
