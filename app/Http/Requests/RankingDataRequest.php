<?php

namespace App\Http\Requests;

use App\Game\Data\CharacterRankingQuery;
use App\Game\Data\GuildRankingQuery;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Version97d99i\CharacterClassFilter;
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
            'board' => ['sometimes', Rule::enum(RankingBoard::class)],
            'class' => ['sometimes', 'nullable', Rule::enum(CharacterClassFilter::class)],
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
        return RankingBoard::tryFrom((string) $this->input('board'))
            ?? RankingBoard::Overall;
    }

    /**
     * Creates the typed character query used by SQL and fixture repositories.
     */
    public function characterQuery(): CharacterRankingQuery
    {
        $classFilter = CharacterClassFilter::tryFrom((string) $this->input('class'));
        $board = $this->board();

        if ($board === RankingBoard::Guilds) {
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
}
