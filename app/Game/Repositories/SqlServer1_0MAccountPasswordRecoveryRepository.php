<?php

namespace App\Game\Repositories;

use App\AccountRecovery\Exceptions\PasswordRecoveryRejected;
use App\AccountRecovery\PasswordRecoveryBlockReason;
use App\AccountRecovery\PasswordResetRecipient;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use SensitiveParameter;
use stdClass;

/**
 * Replaces account-bound password hashes in the verified DarksTeam 1.0M schema.
 */
final readonly class SqlServer1_0MAccountPasswordRecoveryRepository implements AccountPasswordRecoveryRepository
{
    /**
     * Creates the adapter with Laravel's game connection manager.
     */
    public function __construct(
        private DatabaseManager $database,
        private DarksTeamMd5PasswordCodec $passwordCodec,
    ) {}

    /**
     * Finds an exact account and email pair without returning password data.
     */
    public function findRecipient(string $accountId, string $email): ?PasswordResetRecipient
    {
        $row = $this->database
            ->connection('game')
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->whereRaw('LOWER(mail_addr) = ?', [Str::lower($email)])
            ->first([
                'memb___id',
                'mail_addr',
            ]);

        if (! $row instanceof stdClass || ! is_string($row->mail_addr)) {
            return null;
        }

        return new PasswordResetRecipient(
            accountId: (string) $row->memb___id,
            email: $row->mail_addr,
        );
    }

    /**
     * Locks the account row and replaces its account-bound legacy password once.
     */
    public function replacePassword(
        string $accountId,
        #[SensitiveParameter] string $newPassword,
    ): void {
        $connection = $this->database->connection('game');

        $connection->transaction(function () use (
            $connection,
            $accountId,
            $newPassword,
        ): void {
            $account = $connection
                ->table('MEMB_INFO')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->first(['memb___id']);

            if (! $account instanceof stdClass) {
                throw new PasswordRecoveryRejected(
                    PasswordRecoveryBlockReason::AccountNotFound,
                    404,
                );
            }

            $passwordHash = strtoupper(bin2hex(
                $this->passwordCodec->encode($accountId, $newPassword),
            ));
            $updatedRows = $connection->update(
                'UPDATE [MEMB_INFO] SET [memb__pwd] = CONVERT(binary(16), ?, 2) WHERE [memb___id] = ?',
                [$passwordHash, $accountId],
            );

            if ($updatedRows !== 1) {
                throw new PasswordRecoveryRejected(
                    PasswordRecoveryBlockReason::OperationFailed,
                    409,
                );
            }
        }, 3);
    }
}
