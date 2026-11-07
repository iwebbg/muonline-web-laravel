<?php

namespace App\Game\Repositories;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\AccountRegistrationResult;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Game\Contracts\AccountRegistrationRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use RuntimeException;
use SensitiveParameter;

/**
 * Creates complete legacy accounts against the verified Darksteam 1.0M schema.
 */
final readonly class SqlServer1_0MAccountRegistrationRepository implements AccountRegistrationRepository
{
    /**
     * Creates the repository with Laravel's configured connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Atomically inserts MEMB_INFO with a server-owned MD5 password hash.
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
                $passwordHash = $this->passwordHash(
                    connection: $connection,
                    accountId: $username,
                    password: $password,
                );

                $accountGuid = $connection
                    ->table('MEMB_INFO')
                    ->insertGetId([
                        'memb___id' => $username,
                        'memb__pwd' => $passwordHash,
                        'memb_name' => $username,
                        'sno__numb' => '1111111111111',
                        'mail_addr' => $email,
                        'bloc_code' => '1',
                        'ctl1_code' => '0',
                    ], 'memb_guid');

                return new AccountRegistrationResult(
                    accountGuid: $accountGuid,
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
     * Encodes one password with the DarksTeam account-bound MD5 function.
     */
    private function passwordHash(
        ConnectionInterface $connection,
        string $accountId,
        #[SensitiveParameter] string $password,
    ): string {
        $result = $connection->selectOne(
            'SELECT dbo.UFN_MD5_ENCODEVALUE(?, ?) as password_hash',
            [$password, $accountId],
        );
        $passwordHash = is_object($result)
            ? ($result->password_hash ?? null)
            : null;

        if (! is_string($passwordHash) || strlen($passwordHash) !== 16) {
            throw new RuntimeException('The game server did not encode a valid account password.');
        }

        return $passwordHash;
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
