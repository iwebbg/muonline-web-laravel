<?php

use App\Http\Controllers\Account\AccountCredentialController;
use App\Http\Controllers\Account\CharacterGrandResetController;
use App\Http\Controllers\Account\CharacterMoveController;
use App\Http\Controllers\Account\CharacterPkClearController;
use App\Http\Controllers\Account\CharacterResetController;
use App\Http\Controllers\Account\CharacterStatAllocationController;
use App\Http\Controllers\Account\CharacterStatResetController;
use App\Http\Controllers\Account\GrandResetPreviewController;
use App\Http\Controllers\Account\ItemStorageController;
use App\Http\Controllers\Account\MoveToTownPreviewController;
use App\Http\Controllers\Account\PkClearPreviewController;
use App\Http\Controllers\Account\ResetPreviewController;
use App\Http\Controllers\Account\StatAllocationPreviewController;
use App\Http\Controllers\Account\StatResetPreviewController;
use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ResetProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredAccountController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'home'])
    ->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredAccountController::class, 'create'])
        ->name('register');
    Route::post('/register', [RegisteredAccountController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/account', AccountDashboardController::class)
        ->name('account.dashboard');
    Route::put('/account/credentials', [AccountCredentialController::class, 'update'])
        ->middleware('throttle:account-credentials')
        ->name('account.credentials.update');
    Route::post('/account/reset-preview', ResetPreviewController::class)
        ->middleware('throttle:reset-preview')
        ->name('account.reset-preview');
    Route::post('/account/character-reset', CharacterResetController::class)
        ->middleware('throttle:character-reset')
        ->name('account.character-reset');
    Route::post('/account/grand-reset-preview', GrandResetPreviewController::class)
        ->middleware('throttle:grand-reset-preview')
        ->name('account.grand-reset-preview');
    Route::post('/account/character-grand-reset', CharacterGrandResetController::class)
        ->middleware('throttle:grand-reset')
        ->name('account.character-grand-reset');
    Route::post('/account/stat-allocation-preview', StatAllocationPreviewController::class)
        ->middleware('throttle:stat-allocation-preview')
        ->name('account.stat-allocation-preview');
    Route::post('/account/stat-allocations', CharacterStatAllocationController::class)
        ->middleware('throttle:stat-allocation')
        ->name('account.stat-allocations.store');
    Route::post('/account/stat-reset-preview', StatResetPreviewController::class)
        ->middleware('throttle:stat-reset-preview')
        ->name('account.stat-reset-preview');
    Route::post('/account/character-stat-reset', CharacterStatResetController::class)
        ->middleware('throttle:stat-reset')
        ->name('account.character-stat-reset');
    Route::post('/account/pk-clear-preview', PkClearPreviewController::class)
        ->middleware('throttle:pk-clear-preview')
        ->name('account.pk-clear-preview');
    Route::post('/account/character-pk-clear', CharacterPkClearController::class)
        ->middleware('throttle:pk-clear')
        ->name('account.character-pk-clear');
    Route::post('/account/move-to-town-preview', MoveToTownPreviewController::class)
        ->middleware('throttle:move-to-town-preview')
        ->name('account.move-to-town-preview');
    Route::post('/account/character-move', CharacterMoveController::class)
        ->middleware('throttle:move-to-town')
        ->name('account.character-move');
    Route::post('/account/item-storage', ItemStorageController::class)
        ->middleware('throttle:item-storage')
        ->name('account.item-storage');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])
            ->name('login');
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('auth:admin')->group(function (): void {
        Route::get('/', AdminDashboardController::class)
            ->name('dashboard');
        Route::put('/reset-profile', [ResetProfileController::class, 'update'])
            ->name('reset-profile.update');
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});

Route::post('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', config('localization.supported'))
    ->name('locale.update');
