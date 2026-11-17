<?php

namespace App\Http\Controllers;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\EventSchedules\RealmEventSchedule;
use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Events\EventProfileReader;
use App\Models\NewsPost;
use App\Servers\GameServerDirectory;
use DateTimeImmutable;
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
        private readonly RealmEventSchedule $eventSchedule,
        private readonly GameServerStatus $gameServerStatus,
        private readonly GameServerDirectory $gameServerDirectory,
    ) {}

    /**
     * Displays the public server page with version-normalized realm read data.
     */
    public function home(): View
    {
        $rankingLimit = (int) config('game.ranking_limit');
        $liveOverview = $this->realmReadRepository->overview();
        $serverDirectory = $this->gameServerDirectory->publicData(
            liveOverview: $liveOverview,
            currentServerOnline: $this->gameServerStatus->isOnline(),
        );
        $server = $serverDirectory['current'];
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

        $eventData = $this->eventScheduleData();
        $events = array_slice($eventData['allEvents'], 0, $this->eventProfileReader->homeLimit());
        $additionalEventCount = max(0, count($eventData['allEvents']) - count($events));

        $rankings = [
            'players' => $players,
            'guilds' => $guilds,
        ];
        $latestNews = NewsPost::query()
            ->where('type', NewsPostType::News)
            ->where('status', NewsPostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->latest('id')
            ->limit(3)
            ->get();

        return view('home', [
            'server' => $server,
            'serverDirectory' => $serverDirectory['entries'],
            'events' => $events,
            'additionalEventCount' => $additionalEventCount,
            'eventServerNow' => $eventData['eventServerNow'],
            'rankings' => $rankings,
            'latestNews' => $latestNews,
        ]);
    }

    /**
     * Displays the complete schedule and activities for the active event profile.
     */
    public function events(): View
    {
        $eventData = $this->eventScheduleData();

        return view('events', [
            'events' => $eventData['allEvents'],
            'eventServerNow' => $eventData['eventServerNow'],
            'eventTimezone' => $eventData['eventTimezone'],
            'eventActivities' => $this->eventProfileReader->activities(
                new DateTimeImmutable($eventData['eventServerNow']),
            ),
        ]);
    }

    /**
     * Calculates one timezone-consistent schedule for the home preview or full events page.
     *
     * @return array{allEvents: list<array<string, mixed>>, eventServerNow: string, eventTimezone: string}
     */
    private function eventScheduleData(): array
    {
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

        return [
            'allEvents' => $allEvents,
            'eventServerNow' => $serverNow->format(DATE_ATOM),
            'eventTimezone' => $eventTimezone->getName(),
        ];
    }
}
