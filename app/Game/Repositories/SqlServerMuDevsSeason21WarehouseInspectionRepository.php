<?php

namespace App\Game\Repositories;

use App\Game\Versions\MuDevsSeason21\ItemCodecMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemRecordMuDevsSeason21;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Reads account-owned S21 warehouse bytes for offline commissioning diagnostics.
 */
final readonly class SqlServerMuDevsSeason21WarehouseInspectionRepository
{
    private const STORED_SLOTS = 240;

    /**
     * Creates the read-only inspector independently of the incomplete selectable profile.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodecMuDevsSeason21 $codec,
    ) {}

    /**
     * Reads the main vault or an exact positive ExtWarehouse.Number for one account.
     *
     * @return list<ItemRecordMuDevsSeason21>|null Null means no matching vault exists.
     */
    public function forAccount(string $accountId, int $vaultNumber = 0): ?array
    {
        if ($vaultNumber < 0) {
            throw new InvalidArgumentException('The native warehouse number cannot be negative.');
        }

        $table = $vaultNumber === 0 ? 'warehouse' : 'ExtWarehouse';
        $query = $this->database->connection('game')
            ->table($table)
            ->where('AccountID', $accountId);

        if ($vaultNumber > 0) {
            $query->where('Number', $vaultNumber);
        }

        $warehouse = $query->first(['Items']);

        if ($warehouse === null) {
            return null;
        }

        $binary = $warehouse->Items;

        if (is_resource($binary)) {
            $binary = stream_get_contents($binary);
        }

        if (! is_string($binary)) {
            throw new UnexpectedValueException('The native warehouse has no readable item blob.');
        }

        return $this->codec->decode($binary, self::STORED_SLOTS);
    }
}
