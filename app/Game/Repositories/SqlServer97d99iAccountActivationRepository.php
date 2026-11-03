<?php

namespace App\Game\Repositories;

use App\Game\Contracts\AccountActivationRepository;
use Illuminate\Database\DatabaseManager;

/**
 * Activates Darksteam 97d+99i accounts through the MEMB_INFO block flag.
 */
final readonly class SqlServer97d99iAccountActivationRepository implements AccountActivationRepository
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
