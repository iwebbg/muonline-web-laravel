<?php

namespace Tests\Integration\Game\Repositories;

use App\Game\Data\CharacterRankingQuery;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\StaffRankingsTest;
use Tests\Integration\TestCase;

/** Checks each version's Staff SQL against isolated tables on disposable SQL Server. */
final class StaffSqlServerRankingsTest extends TestCase
{
    /**
     * Provides all four SQL readers without depending on the installation's profile.
     *
     * @return array<string, array{class-string, class-string}>
     */
    public static function profiles(): array
    {
        return StaffRankingsTest::profiles();
    }

    /** Verifies native filtering, pagination and connection state for exact staff codes. */
    #[DataProvider('profiles')]
    public function test_native_staff_queries_work_across_all_profiles(string $readerClass, string $profileClass): void
    {
        config()->set('database.connections.staff_rankings', [
            ...config('database.connections.game'),
            'prefix' => 'staff_ranking_',
        ]);
        config()->set('game.database_connections.rankings', 'staff_rankings');
        $this->app->instance(GameVersionProfile::class, new $profileClass);
        $schema = Schema::connection('staff_rankings');
        $schema->create('Character', function (Blueprint $table): void {
            $table->string('Name', 10)->primary();
            $table->string('AccountID', 10);
            $table->integer('CharacterIndex');
            $table->integer('Class');
            $table->integer('CtlCode');
            $table->integer('cLevel');

            foreach (['Resets', 'GrandResets', 'ResetCount', 'MasterResetCount', 'PkCount', 'PkLevel'] as $column) {
                $table->integer($column)->default(0);
            }
        });
        $schema->create('MEMB_STAT', function (Blueprint $table): void {
            $table->string('memb___id', 10)->primary();
            $table->integer('ConnectStat');
        });
        $schema->create('MasterSkillTree', function (Blueprint $table): void {
            $table->integer('CharacterIndex')->primary();
            $table->integer('MasterLevel');
        });

        try {
            $connection = $this->app->make(DatabaseManager::class)->connection('staff_rankings');
            $connection->table('Character')->insert([
                ['Name' => 'GM8', 'AccountID' => 'staffon', 'CharacterIndex' => 1, 'Class' => 1, 'CtlCode' => 8, 'cLevel' => 400],
                ['Name' => 'GM32', 'AccountID' => 'staffoff', 'CharacterIndex' => 2, 'Class' => 17, 'CtlCode' => 32, 'cLevel' => 350],
                ['Name' => 'Player', 'AccountID' => 'player', 'CharacterIndex' => 3, 'Class' => 1, 'CtlCode' => 0, 'cLevel' => 400],
                ['Name' => 'Flag40', 'AccountID' => 'player', 'CharacterIndex' => 4, 'Class' => 1, 'CtlCode' => 40, 'cLevel' => 400],
            ]);
            $connection->table('MEMB_STAT')->insert(['memb___id' => 'staffon', 'ConnectStat' => 1]);
            $reader = $this->app->make($readerClass);
            $page = $reader->characterRankings(new CharacterRankingQuery(RankingBoard::Staff, null, 1, 1, '', false));
            self::assertSame(2, $page->total);
            self::assertSame('GM32', $page->entries[0]->name);
            self::assertFalse($page->entries[0]->online);
            $online = $reader->characterRankings(new CharacterRankingQuery(RankingBoard::Staff, null, 1, 10, '', true));
            self::assertSame(1, $online->total);
            self::assertSame('GM8', $online->entries[0]->name);
            self::assertTrue($online->entries[0]->online);
        } finally {
            $schema->dropIfExists('MasterSkillTree');
            $schema->dropIfExists('MEMB_STAT');
            $schema->dropIfExists('Character');
        }
    }
}
