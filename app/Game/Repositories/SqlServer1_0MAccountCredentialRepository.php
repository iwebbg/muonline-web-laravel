<?php

namespace App\Game\Repositories;

use App\AccountCredentials\AccountCredentialBlockReason;
use App\AccountCredentials\AccountCredentialResult;
use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\Game\Contracts\AccountCredentialRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;
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

            $updates = [];

            if ($emailChanged) {
                $updates['mail_addr'] = $email;
            }

            if ($passwordChanged && $newPassword !== null) {
                $updates['memb__pwd'] = $this->passwordHash(
                    connection: $connection,
                    accountId: $accountId,
                    password: $newPassword,
                );
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

    /**
     * Encodes one replacement password with the server-owned MD5 function.
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
}
