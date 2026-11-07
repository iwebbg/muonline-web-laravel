<?php

namespace App\Game;

/**
 * Identifies password formats implemented by verified game-version adapters.
 */
enum GameAccountPasswordScheme: string
{
    case Plaintext = 'plaintext';
    case DarksTeamMd5 = 'darksteam_md5';
}
