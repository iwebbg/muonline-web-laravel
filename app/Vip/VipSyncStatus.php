<?php

namespace App\Vip;

/**
 * Identifies whether an application VIP entitlement is reflected in MuOnline.
 */
enum VipSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Expired = 'expired';
}
