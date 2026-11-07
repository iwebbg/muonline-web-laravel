<?php

namespace App\Game\Repositories;

use App\AccountCredentials\AccountCredentialBlockReason;
use App\AccountCredentials\AccountCredentialResult;
use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\Game\Contracts\AccountCredentialRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use SensitiveParameter;
use stdClass;

/**
 * Updates credentials against the verified Darksteam 1.0M MEMB_INFO schema.
 */
final readonly class SqlServer1_0MAccountCredentialRepository implements AccountCredentialRepository
{
    /**
     * Creates the repository with Laravel's configured connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Locks the account, verifies its current password and applies real changes once.
     */
    public function update(
        string $accountId,
        #[SensitiveParameter] string $currentPassword,
        string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountCredentialResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $currentPassword,
            $email,
            $newPassword,
        ): AccountCredentialResult {
            $account = $connection
                ->table('MEMB_INFO')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->select('mail_addr')
                ->selectRaw(
                    'dbo.UFN_MD5_CHECKVALUE(?, memb___id, memb__pwd) as credential_valid',
                    [$currentPassword],
                )
                ->first();

            if (! $account instanceof stdClass) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::AccountNotFound,
                    404,
                );
            }

            if ((int) $account->credential_valid !== 1) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::CurrentPasswordInvalid,
                );
            }

            $currentEmail = $account->mail_addr === null
                ? null
                : (string) $account->mail_addr;
            $emailChanged = Str::lower((string) $currentEmail) !== Str::lower($email);
            $passwordChanged = $newPassword !== null
                && ! hash_equals($currentPassword, $newPassword);

            if (! $emailChanged && ! $passwordChanged) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::NoChanges,
                );
            }

            if ($passwordChanged && $newPassword !== null) {
                $updatedRows = $emailChanged
                    ? $connection->update(
                        'UPDATE [MEMB_INFO] SET [mail_addr] = ?, [memb__pwd] = dbo.UFN_MD5_ENCODEVALUE(?, ?) WHERE [memb___id] = ?',
                        [$email, $newPassword, $accountId, $accountId],
                    )
                    : $connection->update(
                        'UPDATE [MEMB_INFO] SET [memb__pwd] = dbo.UFN_MD5_ENCODEVALUE(?, ?) WHERE [memb___id] = ?',
                        [$newPassword, $accountId, $accountId],
                    );
            } else {
                $updatedRows = $connection
                    ->table('MEMB_INFO')
                    ->where('memb___id', $accountId)
                    ->update(['mail_addr' => $email]);
            }

            if ($updatedRows !== 1) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::OperationInProgress,
                    409,
                );
            }

            return new AccountCredentialResult(
                accountId: $accountId,
                emailChanged: $emailChanged,
                passwordChanged: $passwordChanged,
                emailPreviouslyPresent: $currentEmail !== null && $currentEmail !== '',
                emailCurrentlyPresent: $email !== '',
            );
        }, 3);
    }
}
