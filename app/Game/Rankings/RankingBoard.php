<?php

namespace App\Game\Rankings;

/**
 * Identifies each independently ordered public leaderboard.
 */
enum RankingBoard: string
{
    case Overall = 'overall';
    case Staff = 'staff';
    case PlayerKillers = 'pk';
    case Guilds = 'guilds';
    case CastleSiege = 'castle_siege';
    case Gens = 'gens';
}
