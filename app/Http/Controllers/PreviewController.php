<?php

namespace App\Http\Controllers;

use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Events\EventProfileReader;
use App\Game\Events\EventSchedule;
use DateTimeZone;
use Illuminate\Contracts\View\View;

/**
 * Provides fixture-backed screens for the first interactive product shell.
 *
 * These fixtures let the design and interaction model evolve before the
 * application receives read or write credentials for a MuOnline database.
 */
class PreviewController extends Controller
{
    /**
     * Creates the preview controller with the configured read-only realm source.
     */
    public function __construct(
        private readonly RealmReadRepository $realmReadRepository,
        private readonly RealmClock $realmClock,
        private readonly EventProfileReader $eventProfileReader,
        private readonly EventSchedule $eventSchedule,
    ) {}

    /**
     * Displays the public server page with version-normalized realm read data.
     */
    public function home(): View
    {
        $rankingLimit = (int) config('game.ranking_limit');
        $server = $this->realmReadRepository
            ->overview()
            ->toArray();
        $characterEntries = $this->realmReadRepository->topCharacters($rankingLimit);
        $guildEntries = $this->realmReadRepository->topGuilds($rankingLimit);
        $players = [];
        $guilds = [];

        foreach ($characterEntries as $characterEntry) {
            $players[] = $characterEntry->toArray();
        }

        foreach ($guildEntries as $guildEntry) {
            $guilds[] = $guildEntry->toArray();
        }

        $eventTimezone = new DateTimeZone((string) config('game.event_timezone'));
        $serverNow = $this->realmClock
            ->now()
            ->setTimezone($eventTimezone);
        $eventEntries = $this->eventSchedule->upcoming(
            definitions: $this->eventProfileReader->definitions(),
            now: $serverNow,
        );
        $allEvents = [];

        foreach ($eventEntries as $eventEntry) {
            $allEvents[] = $eventEntry->toClientArray();
        }

        $events = array_slice($allEvents, 0, $this->eventProfileReader->homeLimit());
        $additionalEventCount = max(0, count($allEvents) - count($events));

        $rankings = [
            'players' => $players,
            'guilds' => $guilds,
        ];

        return view('home', [
            'server' => $server,
            'events' => $events,
            'allEvents' => $allEvents,
            'additionalEventCount' => $additionalEventCount,
            'eventServerNow' => $serverNow->format(DATE_ATOM),
            'eventTimezone' => $eventTimezone->getName(),
            'rankings' => $rankings,
        ]);
    }
}
