<?php

namespace App\Installation;

/**
 * Describes safe uninstall progress without exposing SQL exceptions or credentials.
 */
final readonly class ApplicationDatabaseRemovalResult
{
    /**
     * Retains independent resource states so a manager can retry partial completion.
     */
    public function __construct(
        public bool $ok,
        public string $code,
        public string $databaseStatus = 'not_checked',
        public string $loginStatus = 'not_checked',
        public bool $retryable = false,
    ) {}

    /**
     * Exposes only bounded status metadata to the manager process.
     *
     * @return array{ok: bool, code: string, database: string, login: string, retryable: bool}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'code' => $this->code,
            'database' => $this->databaseStatus,
            'login' => $this->loginStatus,
            'retryable' => $this->retryable,
        ];
    }
}
