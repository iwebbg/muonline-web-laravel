<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\DatabaseManager;
use SensitiveParameter;

/**
 * Authenticates MuOnline accounts against the legacy MEMB_INFO table.
 */
final readonly class GameAccountUserProvider implements UserProvider
{
    /**
     * Creates the provider with Laravel's configured database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Retrieves an account when Laravel restores an authenticated session.
     */
    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        return $this->findByUsername((string) $identifier);
    }

    /**
     * Declines persistent-cookie authentication because MEMB_INFO has no token column.
     */
    public function retrieveByToken(mixed $identifier, #[SensitiveParameter] mixed $token): ?Authenticatable
    {
        return null;
    }

    /**
     * Ignores persistent-cookie token updates because remember-me login is disabled.
     */
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] mixed $token): void {}

    /**
     * Retrieves the account candidate for one username and password attempt.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?Authenticatable
    {
        $username = $credentials['username'] ?? null;

        if (! is_string($username) || $username === '') {
            return null;
        }

        return $this->findByUsername($username);
    }

    /**
     * Validates the configured plaintext 97d+99i password and account block state.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;
        $passwordScheme = (string) config('game.account_password_scheme');

        if (! $user instanceof GameAccount || $user->blocked) {
            return false;
        }

        if ($passwordScheme !== 'plaintext' || ! is_string($password)) {
            return false;
        }

        return hash_equals($user->getAuthPassword(), $password);
    }

    /**
     * Performs no rehash because the game server owns this legacy password format.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[SensitiveParameter] array $credentials,
        bool $force = false,
    ): void {}

    /**
     * Reads one minimal authentication record using a bound username condition.
     */
    private function findByUsername(string $username): ?GameAccount
    {
        $record = $this->database
            ->connection('game')
            ->table('MEMB_INFO')
            ->where('memb___id', $username)
            ->first([
                'memb_guid',
                'memb___id',
                'memb__pwd',
                'memb_name',
                'mail_addr',
                'bloc_code',
                'IsVip',
            ]);

        if ($record === null) {
            return null;
        }

        return new GameAccount(
            guid: (int) $record->memb_guid,
            username: (string) $record->memb___id,
            displayName: (string) $record->memb_name,
            email: $record->mail_addr === null ? null : (string) $record->mail_addr,
            blocked: (string) $record->bloc_code === '1',
            vip: (int) $record->IsVip === 1,
            password: (string) $record->memb__pwd,
        );
    }
}
