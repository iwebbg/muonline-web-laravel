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
 * Updates credentials against the verified MuEMU Season 6 MEMB_INFO schema.
 */
final readonly class SqlServerMuEmuSeason6AccountCredentialRepository implements AccountCredentialRepository
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
                ->first([
                    'memb__pwd',
                    'mail_addr',
                ]);

            if (! $account instanceof stdClass) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::AccountNotFound,
                    404,
                );
            }

            $storedPassword = (string) $account->memb__pwd;

            if (! hash_equals($storedPassword, $currentPassword)) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::CurrentPasswordInvalid,
                );
            }

            $currentEmail = $account->mail_addr === null
                ? null
                : (string) $account->mail_addr;
            $emailChanged = Str::lower((string) $currentEmail) !== Str::lower($email);
            $passwordChanged = $newPassword !== null
                && ! hash_equals($storedPassword, $newPassword);

            if (! $emailChanged && ! $passwordChanged) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::NoChanges,
                );
            }

            $updates = [];

            if ($emailChanged) {
                $updates['mail_addr'] = $email;
            }

            if ($passwordChanged && $newPassword !== null) {
                $updates['memb__pwd'] = $newPassword;
            }

            $updatedRows = $connection
                ->table('MEMB_INFO')
                ->where('memb___id', $accountId)
                ->update($updates);

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
