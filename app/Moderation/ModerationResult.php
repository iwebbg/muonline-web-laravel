<?php

namespace App\Moderation;

/**
 * Carries exact before and after state from one committed moderation mutation.
 */
final readonly class ModerationResult
{
    /**
     * Creates one immutable moderation result.
     */
    public function __construct(
        public ModerationTargetType $targetType,
        public string $targetIdentifier,
        public string $accountId,
        public bool $beforeBlocked,
        public bool $afterBlocked,
        public int $beforeControlCode,
        public int $afterControlCode,
    ) {}

    /**
     * Returns the audited state before the game database write.
     *
     * @return array{blocked: bool, control_code: int}
     */
    public function beforeSnapshot(): array
    {
        return [
            'blocked' => $this->beforeBlocked,
            'control_code' => $this->beforeControlCode,
        ];
    }

    /**
     * Returns the audited state after the game database write.
     *
     * @return array{blocked: bool, control_code: int}
     */
    public function afterSnapshot(): array
    {
        return [
            'blocked' => $this->afterBlocked,
            'control_code' => $this->afterControlCode,
        ];
    }
}
