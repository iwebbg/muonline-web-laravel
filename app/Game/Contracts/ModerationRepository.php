<?php

namespace App\Game\Contracts;

use App\Moderation\ModerationAction;
use App\Moderation\ModerationResult;
use App\Moderation\ModerationTarget;

/**
 * Defines version-specific reads and transactional writes for game moderation.
 */
interface ModerationRepository
{
    /**
     * Finds non-sensitive account targets matching an optional identifier fragment.
     *
     * @return list<ModerationTarget>
     */
    public function searchAccounts(?string $search, int $limit, int $offset = 0): array;

    /**
     * Finds account groups matching either an account identifier or character name.
     *
     * @return list<ModerationTarget>
     */
    public function searchAccountGroups(?string $search, int $limit, int $offset = 0): array;

    /**
     * Finds non-sensitive character targets matching an optional name fragment.
     *
     * @return list<ModerationTarget>
     */
    public function searchCharacters(?string $search, int $limit, int $offset = 0): array;

    /**
     * Finds every character owned by the supplied account identifiers.
     *
     * @param  list<string>  $accountIds
     * @return list<ModerationTarget>
     */
    public function charactersForAccounts(array $accountIds): array;

    /**
     * Atomically updates the verified MEMB_INFO bloc_code field.
     */
    public function moderateAccount(
        string $accountId,
        ModerationAction $action,
    ): ModerationResult;

    /**
     * Atomically updates an offline character's verified CtlCode field.
     */
    public function moderateCharacter(
        string $characterName,
        ModerationAction $action,
    ): ModerationResult;
}
