<?php

namespace App\Game\Repositories;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\AccountRegistrationResult;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use RuntimeException;
use SensitiveParameter;
use stdClass;

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
        private DarksTeamMd5PasswordCodec $passwordCodec,
    ) {}

    /**
     * Atomically inserts MEMB_INFO with a GameServer-compatible MD5 password hash.
     */
    public function register(
        string $username,
        #[SensitiveParameter] string $password,
        string $email,
    ): AccountRegistrationResult {
        $connection = $this->database->connection('game');
        $passwordHash = strtoupper(bin2hex($this->passwordCodec->encode($username, $password)));

        try {
            return $connection->transaction(function () use (
                $connection,
                $username,
                $passwordHash,
                $email,
            ): AccountRegistrationResult {
                $this->rejectExistingAccount($connection, $username);
                $insertedAccount = $connection->selectOne(
                    <<<'SQL'
                        INSERT INTO [MEMB_INFO] (
                            [memb___id], [memb__pwd], [memb_name], [sno__numb],
                            [mail_addr], [bloc_code], [ctl1_code]
                        )
                        OUTPUT INSERTED.[memb_guid] AS [account_guid]
                        VALUES (?, CONVERT(binary(16), ?, 2), ?, ?, ?, ?, ?)
                    SQL,
                    [
                        $username,
                        $passwordHash,
                        $username,
                        '1111111111111',
                        $email,
                        '1',
                        '0',
                    ],
                );

                if (! $insertedAccount instanceof stdClass) {
                    throw new RuntimeException('The game server did not return the created account identifier.');
                }

                return new AccountRegistrationResult(
                    accountGuid: (int) $insertedAccount->account_guid,
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
