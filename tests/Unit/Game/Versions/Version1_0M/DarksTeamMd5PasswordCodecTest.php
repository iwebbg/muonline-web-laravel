<?php

namespace Tests\Unit\Game\Versions\Version1_0M;

use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies binary compatibility with original WZ MD5 account hashes.
 */
final class DarksTeamMd5PasswordCodecTest extends TestCase
{
    /**
     * Provides hashes accepted by an original DarksTeam 1.0M GameServer.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function gameServerPasswordVectors(): iterable
    {
        yield 'existing iwebbg account' => [
            'iwebbg',
            'test',
            'C7B32BD7928AB0BF4D6BA098C3CB8AD4',
        ];

        yield 'existing test account' => [
            'test',
            'test',
            '9D5589FE2D43CCAC409F929D0DA83E87',
        ];

    }

    /**
     * Confirms account-bound hashes match known GameServer and WZ outputs.
     */
    #[DataProvider('gameServerPasswordVectors')]
    public function test_encodes_passwords_accepted_by_the_game_server(
        string $accountId,
        string $password,
        string $passwordHash,
    ): void {
        $codec = new DarksTeamMd5PasswordCodec;

        $encodedPassword = $codec->encode($accountId, $password);

        $this->assertSame($passwordHash, strtoupper(bin2hex($encodedPassword)));
        $this->assertTrue($codec->verify($accountId, $password, $encodedPassword));
    }

    /**
     * Confirms one password produces distinct hashes for distinct account IDs.
     */
    public function test_binds_the_password_hash_to_the_account_id(): void
    {
        $codec = new DarksTeamMd5PasswordCodec;

        $firstHash = $codec->encode('iwebbg', 'test');
        $secondHash = $codec->encode('test', 'test');

        $this->assertNotSame($firstHash, $secondHash);
    }

    /**
     * Rejects malformed database values before performing a credential comparison.
     */
    public function test_rejects_a_hash_that_is_not_sixteen_bytes(): void
    {
        $codec = new DarksTeamMd5PasswordCodec;

        $isValid = $codec->verify('test', 'test', 'not-a-binary-hash');

        $this->assertFalse($isValid);
    }
}
