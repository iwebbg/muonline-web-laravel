<?php

namespace App\Game\Clocks;

use App\Game\Contracts\RealmClock;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Reads the authoritative current instant from the remote MuOnline SQL Server.
 */
final readonly class SqlServerRealmClock implements RealmClock
{
    /**
     * Creates the clock with Laravel's configured database manager.
     */
    public function __construct(
        private DatabaseManager $database,
        private SystemRealmClock $fallbackClock,
        private LoggerInterface $logger,
    ) {}

    /**
     * Returns SQL Server time including the database server's UTC offset.
     */
    public function now(): DateTimeImmutable
    {
        try {
            return $this->databaseNow();
        } catch (Throwable $exception) {
            $this->logger->warning('Falling back to the web server clock because the SQL Server clock is unavailable.', [
                'exception_class' => $exception::class,
            ]);

            return $this->fallbackClock->now();
        }
    }

    /**
     * Reads and parses the current SQL Server timestamp.
     *
     * @throws Throwable When the game connection or returned timestamp is invalid.
     */
    private function databaseNow(): DateTimeImmutable
    {
        $row = $this->database
            ->connection('game')
            ->selectOne('SELECT SYSDATETIMEOFFSET() AS server_now');
        $serverNow = $row?->server_now ?? null;

        if (! is_string($serverNow) || $serverNow === '') {
            throw new RuntimeException('SQL Server did not return an authoritative realm timestamp.');
        }

        return new DateTimeImmutable($serverNow);
    }
}
