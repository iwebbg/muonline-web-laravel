<?php

namespace Tests\Integration1_0M\Auth;

use App\Auth\GameAccount;
use App\Auth\GameAccountUserProvider;
use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies 1.0M authentication without legacy SQL extended procedures.
 */
final class GameAccountUserProviderTest extends TestCase
{
    private const ACCOUNT_ID = 'test';

    /**
     * Removes only the disposable authentication account owned by this test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $this->gameConnection()
                ->table('MEMB_INFO')
                ->where('memb___id', self::ACCOUNT_ID)
                ->delete();
        }

        parent::tearDown();
    }

    /**
     * Accepts a password hash copied from an account verified by the GameServer.
     */
    public function test_accepts_a_game_server_compatible_password(): void
    {
        $this->insertKnownGameServerAccount();
        $provider = $this->provider();
        $account = $provider->retrieveByCredentials(['username' => self::ACCOUNT_ID]);

        $isValid = $provider->validateCredentials($account, ['password' => 'test']);

        $this->assertInstanceOf(GameAccount::class, $account);
        $this->assertTrue($isValid);
    }

    /**
     * Rejects a password that does not produce the stored account-bound hash.
     */
    public function test_rejects_an_invalid_game_server_password(): void
    {
        $this->insertKnownGameServerAccount();
        $provider = $this->provider();
        $account = $provider->retrieveByCredentials(['username' => self::ACCOUNT_ID]);

        $isValid = $provider->validateCredentials($account, ['password' => 'wrong']);

        $this->assertInstanceOf(GameAccount::class, $account);
        $this->assertFalse($isValid);
    }

    /**
     * Inserts the known test/test binary hash accepted by the real JoinServer.
     */
    private function insertKnownGameServerAccount(): void
    {
        $this->gameConnection()->statement(
            <<<'SQL'
                INSERT INTO [MEMB_INFO] (
                    [memb___id], [memb__pwd], [memb_name], [sno__numb],
                    [mail_addr], [bloc_code], [ctl1_code]
                ) VALUES (?, CONVERT(binary(16), ?, 2), ?, ?, ?, ?, ?)
            SQL,
            [
                self::ACCOUNT_ID,
                '9D5589FE2D43CCAC409F929D0DA83E87',
                self::ACCOUNT_ID,
                '1111111111111',
                'test@example.test',
                '0',
                '0',
            ],
        );
    }

    /**
     * Creates the production user provider with the active 1.0M dependencies.
     */
    private function provider(): GameAccountUserProvider
    {
        config()->set('game.account_password_scheme', 'darksteam_md5');

        return new GameAccountUserProvider(
            database: $this->app->make(DatabaseManager::class),
            versionProfile: new Version1_0MProfile,
            darksTeamMd5PasswordCodec: new DarksTeamMd5PasswordCodec,
        );
    }

    /**
     * Returns the disposable legacy game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
