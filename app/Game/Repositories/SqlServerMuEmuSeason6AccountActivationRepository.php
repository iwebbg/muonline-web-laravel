<?php

namespace App\Game\Repositories;

use App\Game\Contracts\AccountActivationRepository;
use Illuminate\Database\DatabaseManager;

/**
 * Activates MuEMU Season 6 accounts through the MEMB_INFO block flag.
 */
final readonly class SqlServerMuEmuSeason6AccountActivationRepository implements AccountActivationRepository
{
    /**
     * Creates the repository with Laravel's configured connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Changes bloc_code from blocked to active for one exact account identifier.
     */
    public function activate(string $accountId): bool
    {
        $connection = $this->database->connection('game');
        $updatedRows = $connection
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->where('bloc_code', '1')
            ->update(['bloc_code' => '0']);

        if ($updatedRows === 1) {
            return true;
        }

        return $connection
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->where('bloc_code', '0')
            ->exists();
    }
}
