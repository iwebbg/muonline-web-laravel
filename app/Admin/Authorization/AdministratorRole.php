<?php

namespace App\Admin\Authorization;

/**
 * Identifies the privileged identity class stored in the application database.
 */
enum AdministratorRole: string
{
    case Administrator = 'administrator';
    case GameMaster = 'game_master';
}
