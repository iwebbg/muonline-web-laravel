<?php

namespace App\Admin\Authorization;

/**
 * Identifies one granular administrative capability granted to a Game Master.
 */
enum AdministratorPermission: string
{
    case ManageSettings = 'manage_settings';
    case ViewAuditLog = 'view_audit_log';
    case ModerateAccounts = 'moderate_accounts';
    case ModerateCharacters = 'moderate_characters';
    case GrantItems = 'grant_items';
    case ManageContent = 'manage_content';
    case ManageEconomy = 'manage_economy';
}
