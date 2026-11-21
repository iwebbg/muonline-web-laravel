<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Database\Connection;
use RuntimeException;
use stdClass;

/**
 * Changes native identity only after preserving stable indexes and all reviewed mutable references.
 */
final readonly class CharacterIdentityMuDevsSeason21
{
    /**
     * Supplies exact class stages and private inter-server participation guards.
     */
    public function __construct(private GameVersionProfile $profile, private BattleCoreReadRepository $battleCore) {}

    /**
     * Returns the explicitly reviewed mutable name graph from the supplied native SQL schema.
     *
     * @return list<array{table: string, column: string, identity_column: string|null, account_column: string|null, realm_column: string|null, optional?: bool}>
     */
    public static function references(): array
    {
        $path = __DIR__.'/../../../../resources/game/mudevs_season_21/identity-references.json';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The native identity reference graph is unavailable.');
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR)['references'];
    }

    /**
     * Returns every column required by native identity checks and reference updates.
     *
     * @return array<string, list<string>>
     */
    public static function requiredSchema(): array
    {
        $tables = [];
        foreach (self::references() as $reference) {
            if ($reference['optional'] ?? false) {
                continue;
            }
            $table = $reference['table'];
            $tables[$table] = array_values(array_unique(array_merge($tables[$table] ?? [], array_filter([
                $reference['column'], $reference['identity_column'], $reference['account_column'], $reference['realm_column'],
            ]))));
        }
        $tables['RestoreOffline'][] = 'Status';
        $tables['OptionData'][] = 'ChangeSkin';
        $tables['PartyRenewalMemberList'][] = 'Class';
        $tables['T_PartyMatching'][] = 'LeaderClass';
        $tables['T_WaitPartyMatching'][] = 'Class';

        return $tables;
    }

    /**
     * Limits identity grants to mutable name caches and reviewed class presentation columns.
     *
     * @return array<string, list<string>>
     */
    public static function updateColumns(): array
    {
        $tables = [];
        foreach (self::references() as $reference) {
            $tables[$reference['table']][] = $reference['column'];
        }
        $tables['OptionData'][] = 'ChangeSkin';
        $tables['PartyRenewalMemberList'][] = 'Class';
        $tables['T_PartyMatching'][] = 'LeaderClass';
        $tables['T_WaitPartyMatching'][] = 'Class';

        return $tables;
    }

    /**
     * Renames owned data by index and secondary social references by the globally unique old name.
     */
    public function rename(Connection $connection, string $accountId, string $oldName, string $newName): CharacterServiceResult
    {
        $this->assertOffline($connection, $accountId);
        $this->assertNoInterServerWork($accountId);
        if (preg_match('/\A[A-Za-z0-9_-]{1,10}\z/', $newName) !== 1) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidName);
        }
        if (strcasecmp($oldName, $newName) === 0) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::SameName);
        }
        $this->lockNativeNames($connection);
        $character = $this->character($connection, $accountId, $oldName);
        if ($connection->table('Character')->whereRaw('LOWER(Name) = ?', [strtolower($newName)])->exists()) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::NameTaken);
        }
        $index = (int) $character->CharacterIndex;
        for ($slot = 1; $slot <= 10; $slot++) {
            $connection->table('AccountCharacter')->where('Id', $accountId)
                ->where(function ($query) use ($slot, $index, $oldName): void {
                    $query->where('GameID'.$slot, $oldName)->orWhere('CharacterIndex_'.$slot, $index);
                })->update(['GameID'.$slot => $newName]);
        }
        $connection->table('AccountCharacter')->where('Id', $accountId)->where('GameIDC', $oldName)->update(['GameIDC' => $newName]);
        foreach (self::references() as $reference) {
            if (($reference['optional'] ?? false) && ! $connection->getSchemaBuilder()->hasTable($reference['table'])) {
                continue;
            }
            $query = $connection->table($reference['table']);
            if ($reference['identity_column'] !== null) {
                $query->where($reference['identity_column'], $index);
            } else {
                $query->where($reference['column'], $oldName);
            }
            if ($reference['account_column'] !== null) {
                $query->where($reference['account_column'], $accountId);
            }
            if ($reference['realm_column'] !== null) {
                $query->whereIn($reference['realm_column'], (array) config('game.battle_core.server_codes', []));
            }
            $query->update([$reference['column'] => $newName]);
        }
        $updated = $connection->table('Character')->where('AccountID', $accountId)->where('CharacterIndex', $index)
            ->where('Name', $oldName)->update(['Name' => $newName]);
        if ($updated !== 1) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress);
        }

        return new CharacterServiceResult(
            ['name' => $oldName, 'character_index' => $index, 'class_code' => (int) $character->Class],
            ['name' => $newName, 'character_index' => $index, 'class_code' => (int) $character->Class],
        );
    }

    /**
     * Changes class without granting advancement or discarding allocated advanced skills and equipped items.
     */
    public function changeClass(Connection $connection, string $accountId, string $name, int $targetCode): CharacterServiceResult
    {
        $this->assertOffline($connection, $accountId);
        $this->assertNoInterServerWork($accountId);
        $character = $this->character($connection, $accountId, $name);
        $current = $this->profile->classForCode((int) $character->Class);
        $target = $this->profile->classForCode($targetCode);
        if ($current === null || $target === null || $target->evolutionTier > $current->evolutionTier) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
        }
        if ($current->family === $target->family) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::SameClassFamily);
        }
        $inventory = $this->binary($character->Inventory);
        if ($inventory !== null && (strlen($inventory) !== 5975
            || substr($inventory, 0, 300) !== str_repeat("\xFF", 300)
            || substr($inventory, 236 * 25, 75) !== str_repeat("\xFF", 75))) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::EquippedItems);
        }
        $index = (int) $character->CharacterIndex;
        foreach ([
            'MasterSkillTree' => ['MasterSkill'],
            'EnhanceSkillTree' => ['EnhanceSkill', 'EnhanceSkillPassive'],
            'AbilityEnhancementCore' => ['Inventory'],
        ] as $table => $columns) {
            foreach ($connection->table($table)->where('CharacterIndex', $index)->lockForUpdate()->get($columns) as $row) {
                foreach ($columns as $column) {
                    $binary = $this->binary($row->{$column});
                    if ($binary !== null && trim($binary, "\x00\xFF") !== '') {
                        throw new CharacterServiceRejected(CharacterServiceBlockReason::AllocatedSkills);
                    }
                }
            }
        }
        $magic = $this->binary($character->MagicList);
        if ($connection->getDriverName() === 'sqlsrv') {
            $connection->update(
                'UPDATE [Character] SET [Class] = ?, [MagicList] = CONVERT(varbinary(246), ?, 2) WHERE [AccountID] = ? AND [CharacterIndex] = ?',
                [$targetCode, str_repeat('FF', 246), $accountId, $index],
            );
        } else {
            $connection->table('Character')->where('AccountID', $accountId)->where('CharacterIndex', $index)
                ->update(['Class' => $targetCode, 'MagicList' => str_repeat("\xFF", 246)]);
        }
        $connection->table('PartyRenewalMemberList')->where('Name', $name)->update(['Class' => $targetCode]);
        $connection->table('T_PartyMatching')->where('PartyLeaderName', $name)->update(['LeaderClass' => $targetCode]);
        $connection->table('T_WaitPartyMatching')->where('MemberName', $name)->update(['Class' => $targetCode]);
        $connection->table('OptionData')->where('CharacterIndex', $index)->update(['ChangeSkin' => 0]);

        return new CharacterServiceResult(
            ['name' => $name, 'class_code' => $current->code, 'character_index' => $index, 'magic_list_hex' => $magic === null ? '' : bin2hex($magic)],
            ['name' => $name, 'class_code' => $targetCode, 'character_index' => $index, 'normal_skills_cleared' => 1],
        );
    }

    /**
     * Rejects connected accounts and restored offline activities before any identity mutation.
     */
    private function assertOffline(Connection $connection, string $accountId): void
    {
        if (GameAccountWriteStatus::blocksWrites($connection->table('MEMB_STAT')->where('memb___id', $accountId)->value('ConnectStat'))
            || $connection->table('RestoreOffline')->where('Account', $accountId)->where(function ($query): void {
                $query->whereNull('Status')->orWhere('Status', '<>', 0);
            })->exists()) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterOnline);
        }
    }

    /**
     * Prevents renaming or class changes while copied inter-server identities or unclaimed rewards are active.
     */
    private function assertNoInterServerWork(string $accountId): void
    {
        $snapshot = $this->battleCore->forAccount($accountId);
        if ($snapshot === null || ! $snapshot->available) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::IdentityDependencyUnavailable);
        }
        if ($snapshot->unclaimedRewardItems > 0) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InterServerBusy);
        }
        foreach ($snapshot->registrations as $registration) {
            if ($registration['registered'] !== false) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::InterServerBusy);
            }
        }
    }

    /**
     * Locks an owned current identity without trusting cached names in dependent tables.
     */
    private function character(Connection $connection, string $accountId, string $name): stdClass
    {
        $row = $connection->table('Character')->where('AccountID', $accountId)->where('Name', $name)
            ->lockForUpdate()->first(['Name', 'Class', 'CharacterIndex', 'Inventory', 'MagicList']);
        if ($row === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }

        return $row;
    }

    /**
     * Serializes native name reservations across accounts before testing case-insensitive availability.
     */
    private function lockNativeNames(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }
        $result = $connection->selectOne("DECLARE @result int; EXEC @result = sys.sp_getapplock @Resource = 'IWEB:S21:character-name', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 5000; SELECT @result AS result");
        if ((int) $result->result < 0) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress);
        }
    }

    /**
     * Reads binary snapshots without transcoding SQL Server stream values.
     */
    private function binary(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if (is_resource($value)) {
            $binary = stream_get_contents($value);
            if ($binary !== false) {
                return $binary;
            }
        }

        throw new RuntimeException('The native identity snapshot contains unsupported binary data.');
    }
}
