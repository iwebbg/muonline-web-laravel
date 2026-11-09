<?php

namespace App\Http\Controllers;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\GensMembershipRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingPage;
use App\Game\Data\GensRankingPage;
use App\Game\Data\GuildRankingPage;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\Requests\RankingDataRequest;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Displays and serves the interactive public ranking explorer.
 */
final class RankingsController extends Controller
{
    /**
     * Creates the controller with the active version class catalog.
     */
    public function __construct(
        private readonly GameVersionProfile $versionProfile,
    ) {}

    /**
     * Displays the explorer with its first server-rendered data page.
     */
    public function __invoke(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
        CastleSiegeReadRepository $castleSiegeReadRepository,
        GensMembershipRepository $gensMembershipRepository,
    ): View {
        return view('rankings', [
            'rankingPage' => $this->payload(
                $request,
                $realmReadRepository,
                $castleSiegeReadRepository,
                $gensMembershipRepository,
            ),
            'rankingDataUrl' => route('rankings.data'),
            'rankingBoards' => $this->rankingBoards(),
            'classFilters' => $this->classFilters(),
        ]);
    }

    /**
     * Returns one validated ranking page for asynchronous navigation.
     */
    public function data(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
        CastleSiegeReadRepository $castleSiegeReadRepository,
        GensMembershipRepository $gensMembershipRepository,
    ): JsonResponse {
        return response()->json($this->payload(
            $request,
            $realmReadRepository,
            $castleSiegeReadRepository,
            $gensMembershipRepository,
        ));
    }

    /**
     * Builds the common response for the initial view and asynchronous calls.
     *
     * @return array<string, mixed>
     */
    private function payload(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
        CastleSiegeReadRepository $castleSiegeReadRepository,
        GensMembershipRepository $gensMembershipRepository,
    ): array {
        $board = $request->board();

        if ($board === RankingBoard::CastleSiege) {
            return $this->castleSiegePayload(
                $request,
                $realmReadRepository,
                $castleSiegeReadRepository,
            );
        }

        if ($board === RankingBoard::Guilds) {
            return $this->guildPayload(
                $board,
                $realmReadRepository->guildRankings($request->guildQuery()),
                $request,
            );
        }

        if ($board === RankingBoard::Gens) {
            return $this->gensPayload(
                $gensMembershipRepository->rankings($request->gensQuery()),
                $request,
            );
        }

        return $this->characterPayload(
            $board,
            $realmReadRepository->characterRankings($request->characterQuery()),
            $request,
        );
    }

    /**
     * Serializes the authoritative current Castle Siege owner as one guild result.
     *
     * @return array<string, mixed>
     */
    private function castleSiegePayload(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
        CastleSiegeReadRepository $castleSiegeReadRepository,
    ): array {
        $configuration = config('game_events.profiles.'.config('game.event_profile').'.castle_siege', []);
        $mapServerGroup = is_array($configuration)
            ? (int) ($configuration['map_server_group'] ?? 0)
            : 0;
        $timezone = new DateTimeZone((string) config('game.event_timezone', 'Europe/Sofia'));
        $state = $castleSiegeReadRepository->state($mapServerGroup, $timezone);
        $search = trim((string) $request->input('search', ''));
        $ownerGuild = $state?->castleOccupied === true ? $state->ownerGuild : null;
        $guild = $ownerGuild === null
            ? null
            : $realmReadRepository->publicGuild($ownerGuild);
        $matchesSearch = $ownerGuild !== null && ($search === ''
            || str_contains(mb_strtolower($ownerGuild), mb_strtolower($search))
            || ($guild !== null && str_contains(mb_strtolower($guild->master), mb_strtolower($search))));
        $items = [];

        if ($ownerGuild !== null && $matchesSearch) {
            $items[] = [
                'rank' => 1,
                'name' => $ownerGuild,
                'master' => $guild?->master ?? '',
                'score' => $guild?->score ?? 0,
                'members' => $guild === null ? 0 : count($guild->members),
                'mark_colors' => $guild?->markColors ?? [],
                'profile_url' => route('guilds.show', $ownerGuild),
            ];
        }

        $total = count($items);

        return [
            'board' => RankingBoard::CastleSiege->value,
            'class_filter' => '',
            'search' => $search,
            'online_only' => false,
            'items' => $items,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 1,
                'total' => $total,
                'from' => $total,
                'to' => $total,
            ],
        ];
    }

    /**
     * Serializes one character ranking page with absolute positions.
     *
     * @return array<string, mixed>
     */
    private function characterPayload(
        RankingBoard $board,
        CharacterRankingPage $page,
        RankingDataRequest $request,
    ): array {
        $items = [];
        $offset = ($page->page - 1) * $page->perPage;

        foreach ($page->entries as $index => $entry) {
            $items[] = [
                'rank' => $offset + $index + 1,
                ...$entry->toArray(),
                'profile_url' => route('characters.show', $entry->name),
            ];
        }

        return $this->basePayload($board, $page, $items, $request);
    }

    /**
     * Serializes one guild ranking page with absolute positions.
     *
     * @return array<string, mixed>
     */
    private function guildPayload(
        RankingBoard $board,
        GuildRankingPage $page,
        RankingDataRequest $request,
    ): array {
        $items = [];
        $offset = ($page->page - 1) * $page->perPage;

        foreach ($page->entries as $index => $entry) {
            $items[] = [
                'rank' => $offset + $index + 1,
                ...$entry->toArray(),
                'profile_url' => route('guilds.show', $entry->name),
            ];
        }

        return $this->basePayload($board, $page, $items, $request);
    }

    /**
     * Serializes one public Gens ranking page with family labels.
     *
     * @return array<string, mixed>
     */
    private function gensPayload(GensRankingPage $page, RankingDataRequest $request): array
    {
        $items = [];
        $offset = ($page->page - 1) * $page->perPage;

        foreach ($page->entries as $index => $entry) {
            $items[] = [
                'rank' => $offset + $index + 1,
                ...$entry->toArray(),
                'family_name' => __('ui.account.gens_families.'.$entry->family->value),
                'profile_url' => route('characters.show', $entry->name),
            ];
        }

        return $this->basePayload(RankingBoard::Gens, $page, $items, $request);
    }

    /**
     * Adds query state and pagination metadata to a serialized ranking page.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function basePayload(
        RankingBoard $board,
        CharacterRankingPage|GuildRankingPage|GensRankingPage $page,
        array $items,
        RankingDataRequest $request,
    ): array {
        $offset = ($page->page - 1) * $page->perPage;

        return [
            'board' => $board->value,
            'class_filter' => in_array($board, [RankingBoard::Guilds, RankingBoard::Gens], true)
                ? ''
                : (string) $request->input('class', ''),
            'search' => trim((string) $request->input('search', '')),
            'online_only' => ! in_array($board, [RankingBoard::Guilds, RankingBoard::Gens], true)
                && $request->boolean('online'),
            'items' => $items,
            'meta' => [
                'current_page' => $page->page,
                'last_page' => $page->lastPage,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'from' => $page->total === 0 ? 0 : $offset + 1,
                'to' => min($offset + count($items), $page->total),
            ],
        ];
    }

    /**
     * Returns every version-supported class filter for the explorer.
     *
     * @return list<array{value: string, label: string, name: string}>
     */
    private function classFilters(): array
    {
        $filters = [];

        foreach ($this->versionProfile->classes() as $classDefinition) {
            $filters[] = $classDefinition->toFilterArray();
        }

        return $filters;
    }

    /**
     * Returns the public boards supported by the active version profile.
     *
     * @return list<string>
     */
    private function rankingBoards(): array
    {
        $boards = [
            RankingBoard::Overall->value,
            RankingBoard::PlayerKillers->value,
            RankingBoard::Guilds->value,
        ];

        if ($this->versionProfile->capabilities()->castleSiege) {
            $boards[] = RankingBoard::CastleSiege->value;
        }

        if ($this->versionProfile->capabilities()->gens) {
            $boards[] = RankingBoard::Gens->value;
        }

        return $boards;
    }
}
