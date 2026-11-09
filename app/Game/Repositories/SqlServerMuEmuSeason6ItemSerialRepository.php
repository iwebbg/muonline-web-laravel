<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ItemSerialRepository;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Reserves MuEMU Season 6 item serials through its server-owned procedure.
 */
final readonly class SqlServerMuEmuSeason6ItemSerialRepository implements ItemSerialRepository
{
    /**
     * Creates the repository with the configured game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Executes WZ_GetItemSerial and validates its single integer result.
     */
    public function nextSerial(): int
    {
        $rows = $this->database
            ->connection('game')
            ->select('EXEC dbo.WZ_GetItemSerial');
        $firstRow = $rows[0] ?? null;
        $values = is_object($firstRow)
            ? array_values((array) $firstRow)
            : [];
        $serial = isset($values[0])
            ? (int) $values[0]
            : -1;

        if ($serial < 1) {
            throw new RuntimeException('The game server did not reserve a valid item serial.');
        }

        return $serial;
    }
}
