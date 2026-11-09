<?php

namespace App\Http\Requests;

use App\Game\Data\CharacterRankingQuery;
use App\Game\Data\GensRankingQuery;
use App\Game\Data\GuildRankingQuery;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates shareable public ranking filters and pagination bounds.
 */
final class RankingDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'board' => ['sometimes', Rule::in($this->rankingBoardValues())],
            'class' => ['sometimes', 'nullable', Rule::in($this->classFilterKeys())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:2', 'max:25'],
            'search' => ['sometimes', 'nullable', 'string', 'max:50'],
            'online' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Returns the selected leaderboard with Overall as the public default.
     */
    public function board(): RankingBoard
    {
        $board = RankingBoard::tryFrom((string) $this->input('board'));

        return $board !== null && in_array($board->value, $this->rankingBoardValues(), true)
            ? $board
            : RankingBoard::Overall;
    }

    /**
     * Creates the typed character query used by SQL and fixture repositories.
     */
    public function characterQuery(): CharacterRankingQuery
    {
        $classFilter = $this->versionProfile()->classForKey(
            (string) $this->input('class'),
        );
        $board = $this->board();

        if (in_array($board, [RankingBoard::Guilds, RankingBoard::CastleSiege, RankingBoard::Gens], true)) {
            $board = RankingBoard::Overall;
        }

        return new CharacterRankingQuery(
            board: $board,
            classFilter: $classFilter,
            page: max(1, $this->integer('page', 1)),
            perPage: max(2, min($this->integer('per_page', 12), 25)),
            search: trim((string) $this->input('search', '')),
            onlineOnly: $this->boolean('online'),
        );
    }

    /**
     * Creates the typed guild query used by SQL and fixture repositories.
     */
    public function guildQuery(): GuildRankingQuery
    {
        return new GuildRankingQuery(
            page: max(1, $this->integer('page', 1)),
            perPage: max(2, min($this->integer('per_page', 12), 25)),
            search: trim((string) $this->input('search', '')),
        );
    }

    /**
     * Creates the typed query used by version-specific Gens adapters.
     */
    public function gensQuery(): GensRankingQuery
    {
        return new GensRankingQuery(
            page: max(1, $this->integer('page', 1)),
            perPage: max(2, min($this->integer('per_page', 12), 25)),
            search: trim((string) $this->input('search', '')),
        );
    }

    /**
     * Returns every class-filter key accepted by the active game profile.
     *
     * @return list<string>
     */
    private function classFilterKeys(): array
    {
        $keys = [];

        foreach ($this->versionProfile()->classes() as $classDefinition) {
            $keys[] = $classDefinition->key;
        }

        return $keys;
    }

    /**
     * Returns only the leaderboard keys supported by the active game profile.
     *
     * @return list<string>
     */
    private function rankingBoardValues(): array
    {
        $boards = [
            RankingBoard::Overall->value,
            RankingBoard::PlayerKillers->value,
            RankingBoard::Guilds->value,
        ];

        if ($this->versionProfile()->capabilities()->castleSiege) {
            $boards[] = RankingBoard::CastleSiege->value;
        }

        if ($this->versionProfile()->capabilities()->gens) {
            $boards[] = RankingBoard::Gens->value;
        }

        return $boards;
    }

    /**
     * Resolves the active version profile from the application container.
     */
    private function versionProfile(): GameVersionProfile
    {
        return app(GameVersionProfile::class);
    }
}
