<?php

namespace App\Moderation;

/**
 * Carries a non-sensitive moderation search result from the legacy game database.
 */
final readonly class ModerationTarget
{
    /**
     * Creates one account or character moderation target snapshot.
     */
    public function __construct(
        public ModerationTargetType $type,
        public string $identifier,
        public string $accountId,
        public bool $blocked,
        public bool $online,
        public int $controlCode,
    ) {}

    /**
     * Serializes the target for the administrator view without credentials.
     *
     * @return array{type: string, identifier: string, accountId: string, blocked: bool, online: bool, controlCode: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'identifier' => $this->identifier,
            'accountId' => $this->accountId,
            'blocked' => $this->blocked,
            'online' => $this->online,
            'controlCode' => $this->controlCode,
        ];
    }
}
