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
 * Creates complete legacy accounts against the verified Darksteam 97d+99i schema.
 */
final readonly class SqlServer97d99iAccountRegistrationRepository implements AccountRegistrationRepository
{
    /**
     * Creates the repository with Laravel's configured connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Atomically inserts MEMB_INFO and VI_CURR_INFO with legacy-compatible defaults.
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
                        'IsVip' => 0,
                        'VipExpirationTime' => 0,
                    ], 'memb_guid');

                $billingRecordCreated = $connection
                    ->table('VI_CURR_INFO')
                    ->insert([
                        'ends_days' => '2005',
                        'chek_code' => '1',
                        'used_time' => 1234,
                        'memb___id' => $username,
                        'memb_name' => $username,
                        'memb_guid' => $accountGuid,
                        'sno__numb' => '1111111111111',
                        'Bill_Section' => 6,
                        'Bill_Value' => 3,
                        'Bill_Hour' => 6,
                        'Surplus_Point' => 6,
                        'Surplus_Minute' => '2003-11-23 10:36:00',
                        'Increase_Days' => 0,
                    ]);

                return new AccountRegistrationResult(
                    accountGuid: $accountGuid,
                    accountId: $username,
                    billingRecordCreated: $billingRecordCreated,
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
