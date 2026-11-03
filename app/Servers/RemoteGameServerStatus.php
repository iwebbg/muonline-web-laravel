<?php

namespace App\Servers;

/**
 * Carries one validated public status response from a related server website.
 */
final readonly class RemoteGameServerStatus
{
    /**
     * Creates an immutable remote status snapshot.
     */
    public function __construct(
        public bool $reachable,
        public ?bool $online,
        public ?int $onlineUsers,
    ) {}

    /**
     * Returns a safe unavailable state for timeouts and invalid responses.
     */
    public static function unavailable(): self
    {
        return new self(
            reachable: false,
            online: null,
            onlineUsers: null,
        );
    }

    /**
     * Converts the snapshot to scalar cache-safe values.
     *
     * @return array{reachable: bool, online: bool|null, online_users: int|null}
     */
    public function toArray(): array
    {
        return [
            'reachable' => $this->reachable,
            'online' => $this->online,
            'online_users' => $this->onlineUsers,
        ];
    }

    /**
     * Reconstructs a typed snapshot from a scalar cache payload.
     *
     * @param  array{reachable?: mixed, online?: mixed, online_users?: mixed}  $payload
     */
    public static function fromArray(array $payload): self
    {
        $reachable = $payload['reachable'] ?? false;
        $online = $payload['online'] ?? null;
        $onlineUsers = $payload['online_users'] ?? null;

        if (! is_bool($reachable)) {
            return self::unavailable();
        }

        if ($online !== null && ! is_bool($online)) {
            return self::unavailable();
        }

        if ($onlineUsers !== null && (! is_int($onlineUsers) || $onlineUsers < 0)) {
            return self::unavailable();
        }

        return new self(
            reachable: $reachable,
            online: $online,
            onlineUsers: $onlineUsers,
        );
    }
}
