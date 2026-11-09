<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Data\CastleSiegeState;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Reads Castle Siege state from the stock DarksTeam MuEMU Season 6 schema without mutating it.
 */
final readonly class SqlServerMuEmuSeason6CastleSiegeReadRepository implements CastleSiegeReadRepository
{
    /**
     * Creates the repository with Laravel's configured game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Returns one normalized Castle Siege cycle or null when the group is not initialized.
     */
    public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState
    {
        $row = $this->database
            ->connection('game')
            ->table('MuCastle_DATA')
            ->select([
                'MAP_SVR_GROUP as map_server_group',
                'SIEGE_START_DATE as siege_start_date',
                'SIEGE_END_DATE as siege_end_date',
                'SIEGE_ENDED as siege_ended',
                'CASTLE_OCCUPY as castle_occupy',
                'OWNER_GUILD as owner_guild',
            ])
            ->where('MAP_SVR_GROUP', $mapServerGroup)
            ->first();

        if ($row === null) {
            return null;
        }

        $cycleStartsAt = $this->dateTime($row->siege_start_date, $timezone);
        $cycleEndsAt = $this->dateTime($row->siege_end_date, $timezone);

        if ($cycleStartsAt === null || $cycleEndsAt === null || $cycleEndsAt <= $cycleStartsAt) {
            return null;
        }

        $ownerGuild = trim((string) $row->owner_guild);

        return new CastleSiegeState(
            mapServerGroup: (int) $row->map_server_group,
            cycleStartsAt: $cycleStartsAt,
            cycleEndsAt: $cycleEndsAt,
            siegeEnded: (bool) $row->siege_ended,
            castleOccupied: (bool) $row->castle_occupy,
            ownerGuild: $ownerGuild === '' ? null : $ownerGuild,
        );
    }

    /**
     * Normalizes SQL Server datetime values as wall-clock dates in the realm timezone.
     */
    private function dateTime(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s.u');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (Throwable) {
            return null;
        }
    }
}
