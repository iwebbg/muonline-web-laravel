<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Stats\CharacterStats;

/**
 * Maps MUDevs base-family identifiers without assuming persisted advancement codes.
 */
enum ClassFamilyMuDevsSeason21: int
{
    case DarkWizard = 0;
    case DarkKnight = 1;
    case FairyElf = 2;
    case MagicGladiator = 3;
    case DarkLord = 4;
    case Summoner = 5;
    case RageFighter = 6;
    case GrowLancer = 7;
    case RuneWizard = 8;
    case Slayer = 9;
    case GunCrusher = 10;
    case KundunMephis = 11;
    case Lemuria = 12;
    case IllusionKnight = 13;
    case Alchemist = 14;
    case Crusader = 15;

    /**
     * Returns the family column used by the supplied item and quest XML files.
     */
    public function sourceKey(): string
    {
        return match ($this) {
            self::DarkWizard => 'DW',
            self::DarkKnight => 'DK',
            self::FairyElf => 'FE',
            self::MagicGladiator => 'MG',
            self::DarkLord => 'DL',
            self::Summoner => 'SU',
            self::RageFighter => 'RF',
            self::GrowLancer => 'GL',
            self::RuneWizard => 'RW',
            self::Slayer => 'SL',
            self::GunCrusher => 'GC',
            self::KundunMephis => 'KM',
            self::Lemuria => 'LM',
            self::IllusionKnight => 'IK',
            self::Alchemist => 'AL',
            self::Crusader => 'CS',
        };
    }

    /**
     * Returns the level-one attributes from the supplied DefaultClassInfo.txt.
     */
    public function baseStats(): CharacterStats
    {
        return match ($this) {
            self::DarkWizard => new CharacterStats(18, 18, 15, 30),
            self::DarkKnight => new CharacterStats(28, 20, 25, 10),
            self::FairyElf => new CharacterStats(22, 25, 20, 15),
            self::MagicGladiator => new CharacterStats(26, 26, 26, 26),
            self::DarkLord => new CharacterStats(26, 20, 20, 15, ['command' => 25]),
            self::Summoner => new CharacterStats(21, 21, 18, 23),
            self::RageFighter => new CharacterStats(32, 27, 25, 20),
            self::GrowLancer => new CharacterStats(30, 30, 25, 24),
            self::RuneWizard => new CharacterStats(13, 18, 14, 40),
            self::Slayer => new CharacterStats(28, 30, 15, 10),
            self::GunCrusher => new CharacterStats(20, 18, 20, 25),
            self::KundunMephis => new CharacterStats(19, 19, 15, 30),
            self::Lemuria => new CharacterStats(18, 18, 19, 30),
            self::IllusionKnight => new CharacterStats(25, 28, 15, 10),
            self::Alchemist => new CharacterStats(15, 20, 23, 25),
            self::Crusader => new CharacterStats(26, 18, 19, 20),
        };
    }

    /**
     * Returns the normal level-up points configured for this family in Common.dat.
     */
    public function pointsPerLevel(): int
    {
        return match ($this) {
            self::MagicGladiator, self::DarkLord, self::RageFighter, self::GrowLancer => 7,
            default => 5,
        };
    }
}
