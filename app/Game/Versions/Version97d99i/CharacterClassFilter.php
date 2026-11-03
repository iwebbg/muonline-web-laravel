<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Identifies every playable 97d+99i class code exposed by ranking filters.
 */
enum CharacterClassFilter: string
{
    case DarkWizard = 'dw';
    case SoulMaster = 'sm';
    case DarkKnight = 'dk';
    case BladeKnight = 'bk';
    case FairyElf = 'fe';
    case MuseElf = 'me';
    case MagicGladiator = 'mg';

    /**
     * Returns the exact Character.Class value represented by the badge.
     */
    public function classCode(): int
    {
        return match ($this) {
            self::DarkWizard => 0,
            self::SoulMaster => 1,
            self::DarkKnight => 16,
            self::BladeKnight => 17,
            self::FairyElf => 32,
            self::MuseElf => 33,
            self::MagicGladiator => 48,
        };
    }

    /**
     * Returns the short badge printed beside ranked characters.
     */
    public function abbreviation(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Returns the full game class name used by accessible filter labels.
     */
    public function className(): string
    {
        return match ($this) {
            self::DarkWizard => 'Dark Wizard',
            self::SoulMaster => 'Soul Master',
            self::DarkKnight => 'Dark Knight',
            self::BladeKnight => 'Blade Knight',
            self::FairyElf => 'Fairy Elf',
            self::MuseElf => 'Muse Elf',
            self::MagicGladiator => 'Magic Gladiator',
        };
    }

    /**
     * Resolves one legacy class code when it belongs to the supported profile.
     */
    public static function fromClassCode(int $classCode): ?self
    {
        foreach (self::cases() as $classFilter) {
            if ($classFilter->classCode() === $classCode) {
                return $classFilter;
            }
        }

        return null;
    }
}
