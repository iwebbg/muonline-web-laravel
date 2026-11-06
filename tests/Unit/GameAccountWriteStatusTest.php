<?php

namespace Tests\Unit;

use App\Game\GameAccountWriteStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies game writes require a positively identified offline account status.
 */
final class GameAccountWriteStatusTest extends TestCase
{
    /**
     * Confirms only the database representations of explicit zero allow writes.
     */
    #[DataProvider('connectionStatuses')]
    public function test_only_explicit_offline_status_allows_game_writes(
        mixed $connectStatus,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            GameAccountWriteStatus::allowsWrites($connectStatus),
        );
        $this->assertSame(
            ! $expected,
            GameAccountWriteStatus::blocksWrites($connectStatus),
        );
    }

    /**
     * Provides verified offline values and every unsafe fallback category.
     *
     * @return array<string, array{mixed, bool}>
     */
    public static function connectionStatuses(): array
    {
        return [
            'integer offline' => [0, true],
            'driver string offline' => ['0', true],
            'online' => [1, false],
            'driver string online' => ['1', false],
            'unexpected status' => [2, false],
            'missing row value' => [null, false],
            'empty value' => ['', false],
            'boolean false' => [false, false],
        ];
    }
}
