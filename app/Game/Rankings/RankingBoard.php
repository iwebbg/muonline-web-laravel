<?php

namespace App\Game\Rankings;

/**
 * Identifies each independently ordered public leaderboard.
 */
enum RankingBoard: string
{
    case Overall = 'overall';
    case PlayerKillers = 'pk';
    case Guilds = 'guilds';
}
