<?php

namespace App\Game\Repositories;

use App\AccountCredentials\AccountCredentialBlockReason;
use App\AccountCredentials\AccountCredentialResult;
use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
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
        private DarksTeamMd5PasswordCodec $passwordCodec,
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
                ->select(['mail_addr', 'memb__pwd'])
                ->first();

            if (! $account instanceof stdClass) {
                throw new AccountCredentialUpdateRejected(
                    AccountCredentialBlockReason::AccountNotFound,
                    404,
                );
            }

            if (! $this->passwordCodec->verify(
                $accountId,
                $currentPassword,
                (string) $account->memb__pwd,
            )) {
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
                $passwordHash = strtoupper(bin2hex(
                    $this->passwordCodec->encode($accountId, $newPassword),
                ));
                $updatedRows = $emailChanged
                    ? $connection->update(
                        'UPDATE [MEMB_INFO] SET [mail_addr] = ?, [memb__pwd] = CONVERT(binary(16), ?, 2) WHERE [memb___id] = ?',
                        [$email, $passwordHash, $accountId],
                    )
                    : $connection->update(
                        'UPDATE [MEMB_INFO] SET [memb__pwd] = CONVERT(binary(16), ?, 2) WHERE [memb___id] = ?',
                        [$passwordHash, $accountId],
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
