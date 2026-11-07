<?php

namespace Tests\Feature;

use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies read-only game database schema and safety diagnostics.
 */
final class GameDatabaseDoctorTest extends TestCase
{
    /**
     * Creates a portable connection with production-safe startup switches.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => true,
        ]);
        config()->set('game.writes_enabled', false);
        $this->app->make(DatabaseManager::class)->purge('game');
    }

    /**
     * Confirms the verified minimum 97d+99i schema passes diagnostics.
     */
    public function test_doctor_accepts_required_schema_with_closed_write_switches(): void
    {
        $this->createRequiredSchema();

        $this->artisan('game:doctor')
            ->expectsOutputToContain('Game database connection is available')
            ->expectsOutputToContain('Required game profile schema is available')
            ->expectsOutputToContain('Game write safety switches are closed')
            ->assertSuccessful();
    }

    /**
     * Confirms a missing required table causes a non-zero production diagnostic.
     */
    public function test_doctor_fails_when_required_schema_is_missing(): void
    {
        $this->createRequiredSchema(includeGuildMember: false);

        $this->artisan('game:doctor')
            ->expectsOutputToContain('Missing required game table: GuildMember')
            ->assertFailed();
    }

    /**
     * Confirms an enabled application write switch fails safe startup diagnostics.
     */
    public function test_doctor_fails_when_write_switch_is_enabled(): void
    {
        $this->createRequiredSchema();
        config()->set('game.writes_enabled', true);

        $this->artisan('game:doctor')
            ->expectsOutputToContain('Unsafe startup flags')
            ->assertFailed();
    }

    /**
     * Creates the minimum tables and columns consumed by the active profile.
     */
    private function createRequiredSchema(bool $includeGuildMember = true): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->statement('CREATE TABLE MEMB_INFO (memb_guid INTEGER, memb___id TEXT, memb__pwd TEXT, mail_addr TEXT, bloc_code INTEGER, ctl1_code INTEGER)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT, Name TEXT, cLevel INTEGER, Class INTEGER, Money INTEGER, Resets INTEGER, GrandResets INTEGER)');
        $connection->statement('CREATE TABLE warehouse (AccountID TEXT, Items BLOB, Money INTEGER)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT, ConnectStat INTEGER)');
        $connection->statement('CREATE TABLE Guild (G_Name TEXT, G_Master TEXT, G_Score INTEGER)');
        $connection->statement('CREATE TABLE GameServerInfo (ItemCount INTEGER)');
        $connection->statement('CREATE TABLE VI_CURR_INFO (memb___id TEXT, memb_guid INTEGER)');

        if ($includeGuildMember) {
            $connection->statement('CREATE TABLE GuildMember (Name TEXT, G_Name TEXT)');
        }
    }
}
