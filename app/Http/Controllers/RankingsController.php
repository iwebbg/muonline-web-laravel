<?php

namespace App\Http\Controllers;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingPage;
use App\Game\Data\GuildRankingPage;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Version97d99i\CharacterClassFilter;
use App\Http\Requests\RankingDataRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Displays and serves the interactive public ranking explorer.
 */
final class RankingsController extends Controller
{
    /**
     * Displays the explorer with its first server-rendered data page.
     */
    public function __invoke(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
    ): View {
        return view('rankings', [
            'rankingPage' => $this->payload($request, $realmReadRepository),
            'rankingDataUrl' => route('rankings.data'),
            'classFilters' => $this->classFilters(),
        ]);
    }

    /**
     * Returns one validated ranking page for asynchronous navigation.
     */
    public function data(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
    ): JsonResponse {
        return response()->json($this->payload($request, $realmReadRepository));
    }

    /**
     * Builds the common response for the initial view and asynchronous calls.
     *
     * @return array<string, mixed>
     */
    private function payload(
        RankingDataRequest $request,
        RealmReadRepository $realmReadRepository,
    ): array {
        $board = $request->board();

        if ($board === RankingBoard::Guilds) {
            return $this->guildPayload(
                $board,
                $realmReadRepository->guildRankings($request->guildQuery()),
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
     * Adds query state and pagination metadata to a serialized ranking page.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function basePayload(
        RankingBoard $board,
        CharacterRankingPage|GuildRankingPage $page,
        array $items,
        RankingDataRequest $request,
    ): array {
        $offset = ($page->page - 1) * $page->perPage;

        return [
            'board' => $board->value,
            'class_filter' => $board === RankingBoard::Guilds
                ? ''
                : (string) $request->input('class', ''),
            'search' => trim((string) $request->input('search', '')),
            'online_only' => $board !== RankingBoard::Guilds && $request->boolean('online'),
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
        return array_map(
            static fn (CharacterClassFilter $classFilter): array => [
                'value' => $classFilter->value,
                'label' => $classFilter->abbreviation(),
                'name' => $classFilter->className(),
            ],
            CharacterClassFilter::cases(),
        );
    }
}
