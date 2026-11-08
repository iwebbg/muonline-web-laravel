<?php

namespace App\Game\Versions\Version1_0M;

use RuntimeException;
use SensitiveParameter;

/**
 * Encodes account-bound passwords exactly as the legacy DarksTeam WZ MD5 module.
 */
final readonly class DarksTeamMd5PasswordCodec
{
    private const array SHIFT_AMOUNTS = [
        7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22,
        5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20,
        4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23,
        6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21,
    ];

    private const array ROUND_CONSTANTS = [
        0xD76AA478, 0xE8C7B756, 0x242070DB, 0xC1BDCEEE,
        0xF57C0FAF, 0x4787C62A, 0xA8304613, 0xFD469501,
        0x698098D8, 0x8B44F7AF, 0xFFFF5BB1, 0x895CD7BE,
        0x6B901122, 0xFD987193, 0xA679438E, 0x49B40821,
        0xF61E2562, 0xC040B340, 0x265E5A51, 0xE9B6C7AA,
        0xD62F105D, 0x02441453, 0xD8A1E681, 0xE7D3FBC8,
        0x21E1CDE6, 0xC33707D6, 0xF4D50D87, 0x455A14ED,
        0xA9E3E905, 0xFCEFA3F8, 0x676F02D9, 0x8D2A4C8A,
        0xFFFA3942, 0x8771F681, 0x6D9D6122, 0xFDE5380C,
        0xA4BEEA44, 0x4BDECFA9, 0xF6BB4B60, 0xBEBFBC70,
        0x289B7EC6, 0xEAA127FA, 0xD4EF3085, 0x04881D05,
        0xD9D4D039, 0xE6DB99E5, 0x1FA27CF8, 0xC4AC5665,
        0xF4292244, 0x432AFF97, 0xAB9423A7, 0xFC93A039,
        0x655B59C3, 0x8F0CCC92, 0xFFEFF47D, 0x85845DD1,
        0x6FA87E4F, 0xFE2CE6E0, 0xA3014314, 0x4E0811A1,
        0xF7537E82, 0xBD3AF235, 0x2AD7D2BB, 0xEB86D391,
    ];

    private string $initializationTable;

    /**
     * Loads the 256 original WZ initialization states used by account-keyed MD5.
     */
    public function __construct()
    {
        $tablePath = dirname(__DIR__, 4).'/resources/game/1_0m/darksteam-md5-initialization-table.b64';
        $encodedTable = file_get_contents($tablePath);

        if (! is_string($encodedTable)) {
            throw new RuntimeException('The DarksTeam MD5 initialization table could not be read.');
        }

        $initializationTable = base64_decode($encodedTable, true);

        if (! is_string($initializationTable) || strlen($initializationTable) !== 4_096) {
            throw new RuntimeException('The DarksTeam MD5 initialization table is invalid.');
        }

        $this->initializationTable = $initializationTable;
    }

    /**
     * Produces the 16-byte password value stored in the 1.0M MEMB_INFO table.
     */
    public function encode(
        string $accountId,
        #[SensitiveParameter] string $password,
    ): string {
        $state = $this->initializationState($this->accountKey($accountId));
        $message = $this->padMessage($password);

        for ($offset = 0; $offset < strlen($message); $offset += 64) {
            $state = $this->compressBlock(
                $state,
                substr($message, $offset, 64),
            );
        }

        return pack('V4', ...$state);
    }

    /**
     * Compares a submitted password with one binary MEMB_INFO password value.
     */
    public function verify(
        string $accountId,
        #[SensitiveParameter] string $password,
        #[SensitiveParameter] string $storedHash,
    ): bool {
        if (strlen($storedHash) !== 16) {
            return false;
        }

        return hash_equals($storedHash, $this->encode($accountId, $password));
    }

    /**
     * Reproduces the JoinServer key with all ten account positions salted by 17.
     */
    private function accountKey(string $accountId): int
    {
        $key = 10 * 17;

        for ($offset = 0; $offset < strlen($accountId); $offset++) {
            $key += ord($accountId[$offset]);
        }

        return $key % 256;
    }

    /**
     * Reads one little-endian four-word initialization state from the WZ table.
     *
     * @return array{int, int, int, int}
     */
    private function initializationState(int $accountKey): array
    {
        $state = unpack(
            'V4',
            substr($this->initializationTable, $accountKey * 16, 16),
        );

        if (! is_array($state)) {
            throw new RuntimeException('The DarksTeam MD5 initialization state could not be decoded.');
        }

        return [
            (int) $state[1],
            (int) $state[2],
            (int) $state[3],
            (int) $state[4],
        ];
    }

    /**
     * Adds standard MD5 padding and the little-endian 64-bit message length.
     */
    private function padMessage(#[SensitiveParameter] string $message): string
    {
        $messageLength = strlen($message);
        $bitLength = $messageLength * 8;
        $paddingLength = (56 - (($messageLength + 1) % 64) + 64) % 64;

        return $message
            ."\x80"
            .str_repeat("\x00", $paddingLength)
            .pack('V2', $bitLength & 0xFFFFFFFF, intdiv($bitLength, 0x100000000));
    }

    /**
     * Applies one standard MD5 compression block with the account-specific state.
     *
     * @param  array{int, int, int, int}  $state
     * @return array{int, int, int, int}
     */
    private function compressBlock(array $state, string $block): array
    {
        $words = unpack('V16', $block);

        if (! is_array($words)) {
            throw new RuntimeException('The DarksTeam MD5 message block could not be decoded.');
        }

        [$stateA, $stateB, $stateC, $stateD] = $state;
        $a = $stateA;
        $b = $stateB;
        $c = $stateC;
        $d = $stateD;

        for ($round = 0; $round < 64; $round++) {
            if ($round < 16) {
                $function = ($b & $c) | ((~$b) & $d);
                $wordIndex = $round;
            } elseif ($round < 32) {
                $function = ($d & $b) | ((~$d) & $c);
                $wordIndex = ((5 * $round) + 1) % 16;
            } elseif ($round < 48) {
                $function = $b ^ $c ^ $d;
                $wordIndex = ((3 * $round) + 5) % 16;
            } else {
                $function = $c ^ ($b | (~$d));
                $wordIndex = (7 * $round) % 16;
            }

            $rotatedValue = $this->rotateLeft(
                ($a + $function + self::ROUND_CONSTANTS[$round] + $words[$wordIndex + 1]) & 0xFFFFFFFF,
                self::SHIFT_AMOUNTS[$round],
            );
            $nextB = ($b + $rotatedValue) & 0xFFFFFFFF;
            $a = $d;
            $d = $c;
            $c = $b;
            $b = $nextB;
        }

        return [
            ($stateA + $a) & 0xFFFFFFFF,
            ($stateB + $b) & 0xFFFFFFFF,
            ($stateC + $c) & 0xFFFFFFFF,
            ($stateD + $d) & 0xFFFFFFFF,
        ];
    }

    /**
     * Rotates one unsigned 32-bit word left without signed integer leakage.
     */
    private function rotateLeft(int $value, int $bits): int
    {
        return (($value << $bits) | ($value >> (32 - $bits))) & 0xFFFFFFFF;
    }
}
