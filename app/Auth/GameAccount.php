<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Represents one authenticated legacy MuOnline account without persisting it locally.
 */
final readonly class GameAccount implements Authenticatable
{
    /**
     * Creates an authenticated account identity from a MEMB_INFO record.
     */
    public function __construct(
        public int $guid,
        public string $username,
        public string $displayName,
        public ?string $email,
        public bool $blocked,
        public bool $vip,
        private string $password,
    ) {}

    /**
     * Returns the legacy column used as the stable session identifier.
     */
    public function getAuthIdentifierName(): string
    {
        return 'memb___id';
    }

    /**
     * Returns the MuOnline account name stored in the authenticated session.
     */
    public function getAuthIdentifier(): string
    {
        return $this->username;
    }

    /**
     * Returns the legacy password column name.
     */
    public function getAuthPasswordName(): string
    {
        return 'memb__pwd';
    }

    /**
     * Returns the legacy password value for credential verification only.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Returns no remember token because the legacy table has no safe token column.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Ignores remember-token writes because persistent login is intentionally unsupported.
     */
    public function setRememberToken(mixed $value): void {}

    /**
     * Returns an empty token name because persistent login is intentionally unsupported.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
