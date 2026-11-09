<?php

namespace App\Game\Repositories;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\AccountRegistrationResult;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Game\Contracts\AccountRegistrationRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use SensitiveParameter;

/**
 * Creates complete legacy accounts against the verified MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6AccountRegistrationRepository implements AccountRegistrationRepository
{
    /**
     * Creates the repository with Laravel's configured connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Atomically inserts MEMB_INFO using the JoinServer's configured plaintext mode.
     */
    public function register(
        string $username,
        #[SensitiveParameter] string $password,
        string $email,
    ): AccountRegistrationResult {
        $connection = $this->database->connection('game');

        try {
            return $connection->transaction(function () use (
                $connection,
                $username,
                $password,
                $email,
            ): AccountRegistrationResult {
                $this->rejectExistingAccount($connection, $username);
                $accountGuid = $connection
                    ->table('MEMB_INFO')
                    ->insertGetId([
                        'memb___id' => $username,
                        'memb__pwd' => $password,
                        'memb_name' => $username,
                        'sno__numb' => '1111111111111',
                        'mail_addr' => $email,
                        'bloc_code' => '1',
                        'ctl1_code' => '0',
                    ], 'memb_guid');

                return new AccountRegistrationResult(
                    accountGuid: (int) $accountGuid,
                    accountId: $username,
                    billingRecordCreated: false,
                );
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new AccountRegistrationRejected(
                    AccountRegistrationBlockReason::AccountAlreadyExists,
                    409,
                );
            }

            throw $exception;
        }
    }

    /**
     * Rejects a known username before attempting the primary-key constrained insert.
     */
    private function rejectExistingAccount(
        ConnectionInterface $connection,
        string $username,
    ): void {
        $accountExists = $connection
            ->table('MEMB_INFO')
            ->where('memb___id', $username)
            ->exists();

        if ($accountExists) {
            throw new AccountRegistrationRejected(
                AccountRegistrationBlockReason::AccountAlreadyExists,
                409,
            );
        }
    }

    /**
     * Recognizes SQL Server and SQLite unique-key failures without parsing messages.
     */
    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $driverErrorCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driverErrorCode, [19, 2601, 2627], true);
    }
}
