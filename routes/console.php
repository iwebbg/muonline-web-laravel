<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Displays a random framework quote in the console.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vip:sync-pending')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('market:sync-escrow')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('web-storage:sync')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('zen-bank:sync')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('credits:collect-online-time')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('credits:sync-item-exchanges')
    ->everyMinute()
    ->withoutOverlapping();
