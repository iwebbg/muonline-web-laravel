<?php

namespace App\Game\Versions\MuEmuSeason6;

/**
 * Owns the MuEMU Update 32 item-option compatibility used by the generator.
 */
final readonly class ItemGeneratorOptionsMuEmuSeason6
{
    public const ANCIENT_FIVE_PRICE = 13;

    public const ANCIENT_TEN_PRICE = 20;

    public const OPTION_380_PRICE = 15;

    public const HARMONY_PRICE = 125;

    public const SOCKET_PRICE = 15;

    /** @var array<int, list<int>> */
    private const ANCIENT_ITEMS = [
        0 => [2, 14, 32],
        2 => [1],
        4 => [5, 9],
        5 => [0],
        6 => [0, 6, 9],
        7 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 26, 40, 59, 62, 63, 64, 65, 66, 67, 68, 69, 70, 72],
        8 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 26, 40, 59, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72],
        9 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 26, 40, 59, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72],
        10 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 26, 40, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71],
        11 => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 26, 40, 59, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72],
        13 => [8, 9, 12, 13, 21, 22, 23, 24, 25, 26, 27, 28],
    ];

    /** @var array<int, list<int>> */
    private const OPTION_380_ITEMS = [
        0 => [22, 23, 35],
        2 => [14],
        4 => [21],
        5 => [12, 19],
        7 => [29, 30, 31, 33, 43, 73],
        8 => [29, 30, 31, 32, 33, 43, 73],
        9 => [29, 30, 31, 32, 33, 43, 73],
        10 => [29, 30, 31, 32, 33, 43],
        11 => [29, 30, 31, 32, 33, 43, 73],
    ];

    /** @var array<int, array<int, int>> */
    private const SOCKET_ITEMS = [
        0 => [26 => 3, 27 => 3, 28 => 3, 29 => 3, 30 => 3, 36 => 3, 37 => 5],
        2 => [16 => 3, 17 => 3, 19 => 3, 20 => 5],
        3 => [12 => 5],
        4 => [23 => 5, 25 => 5, 26 => 5],
        5 => [30 => 3, 31 => 3, 32 => 3, 35 => 3, 37 => 5],
        6 => [17 => 3, 18 => 3, 19 => 3, 20 => 3, 22 => 3, 23 => 3, 24 => 3, 25 => 3, 26 => 3],
        7 => [45 => 3, 46 => 3, 47 => 3, 48 => 3, 49 => 3, 50 => 3, 51 => 3, 52 => 3, 53 => 3, 54 => 3, 55 => 3, 56 => 3, 58 => 3, 74 => 3, 75 => 3],
        8 => [45 => 3, 46 => 3, 47 => 3, 48 => 3, 49 => 3, 50 => 3, 51 => 3, 52 => 3, 53 => 3, 54 => 3, 55 => 3, 56 => 3, 57 => 3, 58 => 3, 74 => 3, 75 => 3, 76 => 3],
        9 => [45 => 3, 46 => 3, 47 => 3, 48 => 3, 49 => 3, 50 => 3, 51 => 3, 52 => 3, 53 => 3, 54 => 3, 55 => 3, 56 => 3, 57 => 3, 58 => 3, 74 => 3, 75 => 3, 76 => 3],
        10 => [45 => 3, 46 => 3, 47 => 3, 48 => 3, 49 => 3, 50 => 3, 51 => 3, 52 => 3, 53 => 3, 54 => 3, 55 => 3, 56 => 3, 57 => 3, 58 => 3, 74 => 3, 75 => 3, 76 => 3],
        11 => [45 => 3, 46 => 3, 47 => 3, 48 => 3, 49 => 3, 50 => 3, 51 => 3, 52 => 3, 53 => 3, 54 => 3, 55 => 3, 56 => 3, 57 => 3, 58 => 3, 74 => 3, 75 => 3, 76 => 3],
    ];

    /** @var array<int, list<int>> */
    private const HARMONY_ITEMS = [
        0 => [0, 1, 2, 4],
        1 => [0, 1, 2],
        2 => [0, 1, 2],
        3 => [1, 2, 3, 5, 6],
        4 => [0, 1, 2, 3, 8, 9, 10, 11],
        5 => [0, 1, 2],
        6 => [0, 1, 2, 3, 4, 6, 7, 9, 10],
        7 => [0, 2, 4, 5, 6, 7, 8, 10, 11, 12],
        8 => [0, 2, 4, 5, 6, 7, 8, 10, 11, 12],
        9 => [0, 2, 4, 5, 6, 7, 8, 10, 11, 12],
        10 => [0, 2, 4, 5, 6, 7, 8, 10, 11, 12],
        11 => [0, 2, 4, 5, 6, 7, 8, 10, 11, 12],
    ];

    /** @var array<string, array<int, int>> */
    private const HARMONY_MINIMUM_LEVELS = [
        'weapon' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 6, 6 => 6, 7 => 9, 8 => 9, 9 => 9, 10 => 13],
        'staff' => [1 => 0, 2 => 0, 3 => 0, 4 => 6, 5 => 6, 6 => 9, 7 => 9, 8 => 13],
        'armor' => [1 => 0, 2 => 3, 3 => 3, 4 => 6, 5 => 9, 6 => 9, 7 => 9, 8 => 13],
    ];

    /**
     * Returns browser-safe compatibility metadata for one catalog item.
     *
     * @return array{ancientOptions: list<int>, allows380: bool, harmonyType: ?string, harmonyOptions: list<array{value: int, minimumLevel: int}>, maxSockets: int}
     */
    public function metadataFor(int $group, int $index): array
    {
        $harmonyType = $this->harmonyType($group, $index);
        $harmonyOptions = [];

        if ($harmonyType !== null) {
            foreach (self::HARMONY_MINIMUM_LEVELS[$harmonyType] as $value => $minimumLevel) {
                $harmonyOptions[] = [
                    'value' => $value,
                    'minimumLevel' => $minimumLevel,
                ];
            }
        }

        return [
            'ancientOptions' => $this->supports(self::ANCIENT_ITEMS, $group, $index) ? [5, 10] : [],
            'allows380' => $this->supports(self::OPTION_380_ITEMS, $group, $index),
            'harmonyType' => $harmonyType,
            'harmonyOptions' => $harmonyOptions,
            'maxSockets' => self::SOCKET_ITEMS[$group][$index] ?? 0,
        ];
    }

    /**
     * Reports whether every advanced option is valid for one Update 32 item.
     *
     * @param  array{ancient_option: int, harmony_option: int, socket_count: int, has_380_option: bool}  $options
     */
    public function optionsAreAllowed(int $group, int $index, array $options): bool
    {
        $metadata = $this->metadataFor($group, $index);
        $ancientOption = $options['ancient_option'];
        $harmonyOption = $options['harmony_option'];
        $socketCount = $options['socket_count'];

        if ($ancientOption !== 0 && ! in_array($ancientOption, $metadata['ancientOptions'], true)) {
            return false;
        }

        if ($options['has_380_option'] && ! $metadata['allows380']) {
            return false;
        }

        if ($socketCount < 0 || $socketCount > $metadata['maxSockets']) {
            return false;
        }

        if ($harmonyOption !== 0 && $socketCount !== 0) {
            return false;
        }

        if ($harmonyOption === 0) {
            return true;
        }

        $harmonyType = $metadata['harmonyType'];
        $harmonyEffect = ($harmonyOption >> 4) & 0x0F;
        $harmonyLevel = $harmonyOption & 0x0F;

        return $harmonyType !== null
            && isset(self::HARMONY_MINIMUM_LEVELS[$harmonyType][$harmonyEffect])
            && $harmonyLevel >= self::HARMONY_MINIMUM_LEVELS[$harmonyType][$harmonyEffect];
    }

    /**
     * Returns trusted advanced-option price components for one quote.
     *
     * @param  array{ancient_option: int, harmony_option: int, socket_count: int, has_380_option: bool}  $options
     * @return array{ancient: int, option_380: int, harmony: int, sockets: int}
     */
    public function priceBreakdown(array $options): array
    {
        $ancientPrice = match ($options['ancient_option']) {
            5 => self::ANCIENT_FIVE_PRICE,
            10 => self::ANCIENT_TEN_PRICE,
            default => 0,
        };

        return [
            'ancient' => $ancientPrice,
            'option_380' => $options['has_380_option'] ? self::OPTION_380_PRICE : 0,
            'harmony' => $options['harmony_option'] !== 0 ? self::HARMONY_PRICE : 0,
            'sockets' => $options['socket_count'] * self::SOCKET_PRICE,
        ];
    }

    /**
     * Returns the native Harmony family supported by one exact item.
     */
    private function harmonyType(int $group, int $index): ?string
    {
        if (! $this->supports(self::HARMONY_ITEMS, $group, $index)) {
            return null;
        }

        return match (true) {
            $group <= 4 => 'weapon',
            $group === 5 => 'staff',
            default => 'armor',
        };
    }

    /**
     * Reports whether one exact item exists in a MuEMU option allow-list.
     *
     * @param  array<int, list<int>>  $items
     */
    private function supports(array $items, int $group, int $index): bool
    {
        return in_array($index, $items[$group] ?? [], true);
    }
}
