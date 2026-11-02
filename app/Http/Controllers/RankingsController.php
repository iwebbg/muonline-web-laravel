<?php

namespace App\Http\Controllers;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use Illuminate\Contracts\View\View;

/**
 * Displays an interactive, read-only ranking explorer for the active realm.
 */
final class RankingsController extends Controller
{
    /**
     * Returns up to one hundred version-normalized player and guild records.
     */
    public function __invoke(RealmReadRepository $realmReadRepository): View
    {
        $characterEntries = $realmReadRepository->topCharacters(100);
        $guildEntries = $realmReadRepository->topGuilds(100);

        return view('rankings', [
            'rankings' => [
                'players' => $this->serializeCharacters($characterEntries),
                'guilds' => $this->serializeGuilds($guildEntries),
            ],
        ]);
    }

    /**
     * Serializes character entries and adds a stable server-side rank.
     *
     * @param  list<CharacterRankingEntry>  $entries
     * @return list<array{rank: int, name: string, class: string, resets: int, grand_resets: int, online: bool, profile_url: string}>
     */
    private function serializeCharacters(array $entries): array
    {
        $players = [];

        foreach ($entries as $index => $entry) {
            $players[] = [
                'rank' => $index + 1,
                ...$entry->toArray(),
                'profile_url' => route('characters.show', $entry->name),
            ];
        }

        return $players;
    }

    /**
     * Serializes guild entries and adds a stable server-side rank.
     *
     * @param  list<GuildRankingEntry>  $entries
     * @return list<array{rank: int, name: string, master: string, score: int, members: int, profile_url: string}>
     */
    private function serializeGuilds(array $entries): array
    {
        $guilds = [];

        foreach ($entries as $index => $entry) {
            $guilds[] = [
                'rank' => $index + 1,
                ...$entry->toArray(),
                'profile_url' => route('guilds.show', $entry->name),
            ];
        }

        return $guilds;
    }
}
