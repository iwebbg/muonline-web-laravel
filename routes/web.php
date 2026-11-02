<?php

use App\Admin\Authorization\AdministratorPermission;
use App\Http\Controllers\Account\AccountCredentialController;
use App\Http\Controllers\Account\AuctionBidController;
use App\Http\Controllers\Account\CharacterGrandResetController;
use App\Http\Controllers\Account\CharacterMoveController;
use App\Http\Controllers\Account\CharacterPkClearController;
use App\Http\Controllers\Account\CharacterResetController;
use App\Http\Controllers\Account\CharacterStatAllocationController;
use App\Http\Controllers\Account\CharacterStatResetController;
use App\Http\Controllers\Account\GrandResetPreviewController;
use App\Http\Controllers\Account\ItemStorageController;
use App\Http\Controllers\Account\JewelBankController;
use App\Http\Controllers\Account\MarketListingController;
use App\Http\Controllers\Account\MarketPurchaseController;
use App\Http\Controllers\Account\MoveToTownPreviewController;
use App\Http\Controllers\Account\PkClearPreviewController;
use App\Http\Controllers\Account\ResetPreviewController;
use App\Http\Controllers\Account\StatAllocationPreviewController;
use App\Http\Controllers\Account\StatResetPreviewController;
use App\Http\Controllers\Account\VipPurchaseController;
use App\Http\Controllers\Account\VoteClaimController;
use App\Http\Controllers\Account\WebStorageController;
use App\Http\Controllers\Account\ZenBankController;
use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DownloadController as AdminDownloadController;
use App\Http\Controllers\Admin\EconomyRecoveryController;
use App\Http\Controllers\Admin\EventScheduleController;
use App\Http\Controllers\Admin\GameMasterController;
use App\Http\Controllers\Admin\ItemGrantController;
use App\Http\Controllers\Admin\MarketController as AdminMarketController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\ModuleProfileController;
use App\Http\Controllers\Admin\NewsPostController as AdminNewsPostController;
use App\Http\Controllers\Admin\ResetProfileController;
use App\Http\Controllers\Admin\VipPlanController;
use App\Http\Controllers\Admin\VoteClaimReviewController;
use App\Http\Controllers\Admin\VoteSiteController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredAccountController;
use App\Http\Controllers\CharacterProfileController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\GuildProfileController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\VoteController;
use App\Modules\ServerModule;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'home'])
    ->name('home');

Route::get('/ready', ReadinessController::class)
    ->name('ready');

Route::get('/rankings', RankingsController::class)
    ->name('rankings');
Route::get('/characters/{characterName}', CharacterProfileController::class)
    ->where('characterName', '[A-Za-z0-9_-]{1,10}')
    ->name('characters.show');
Route::get('/guilds/{guildName}', GuildProfileController::class)
    ->where('guildName', '[^/]{1,50}')
    ->name('guilds.show');

Route::get('/news', [NewsController::class, 'index'])
    ->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('news.show');

Route::get('/guides', [GuideController::class, 'index'])
    ->name('guides.index');
Route::get('/guides/{slug}', [GuideController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('guides.show');

Route::get('/downloads', DownloadController::class)
    ->name('downloads.index');

Route::get('/market', MarketController::class)
    ->middleware('module.enabled:'.ServerModule::Market->value)
    ->name('market.index');

Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
    ->middleware('module.enabled:'.ServerModule::PasswordRecovery->value)
    ->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware([
        'module.enabled:'.ServerModule::PasswordRecovery->value,
        'throttle:password-recovery-link',
    ])
    ->name('password.email');
Route::get('/reset-password/{account}/{token}', [NewPasswordController::class, 'create'])
    ->middleware('module.enabled:'.ServerModule::PasswordRecovery->value)
    ->where('account', '[A-Za-z0-9_-]{4,10}')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware([
        'module.enabled:'.ServerModule::PasswordRecovery->value,
        'throttle:password-recovery-reset',
    ])
    ->name('password.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredAccountController::class, 'create'])
        ->middleware('module.enabled:'.ServerModule::Registration->value)
        ->name('register');
    Route::post('/register', [RegisteredAccountController::class, 'store'])
        ->middleware([
            'module.enabled:'.ServerModule::Registration->value,
            'throttle:registration',
        ])
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
    Route::get('/votes', VoteController::class)
        ->middleware('module.enabled:'.ServerModule::VoteRewards->value)
        ->name('votes.index');
    Route::post('/votes/{voteSite}/claims', VoteClaimController::class)
        ->middleware([
            'module.enabled:'.ServerModule::VoteRewards->value,
            'throttle:vote-claim',
        ])
        ->name('votes.claims.store');
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
    Route::post('/account/vip-purchases', VipPurchaseController::class)
        ->middleware([
            'module.enabled:'.ServerModule::Vip->value,
            'throttle:vip-purchase',
        ])
        ->name('account.vip-purchases.store');
    Route::get('/account/web-storage', [WebStorageController::class, 'index'])
        ->middleware('module.enabled:'.ServerModule::WebStorage->value)
        ->name('account.web-storage.index');
    Route::post('/account/web-storage', [WebStorageController::class, 'store'])
        ->middleware([
            'module.enabled:'.ServerModule::WebStorage->value,
            'throttle:web-storage',
        ])
        ->name('account.web-storage.store');
    Route::delete('/account/web-storage/{webStorageItem}', [WebStorageController::class, 'destroy'])
        ->middleware([
            'module.enabled:'.ServerModule::WebStorage->value,
            'throttle:web-storage',
        ])
        ->name('account.web-storage.destroy');
    Route::get('/account/jewel-bank', [JewelBankController::class, 'index'])
        ->middleware('module.enabled:'.ServerModule::JewelBank->value)
        ->name('account.jewel-bank.index');
    Route::post('/account/jewel-bank', [JewelBankController::class, 'store'])
        ->middleware([
            'module.enabled:'.ServerModule::JewelBank->value,
            'throttle:jewel-bank',
        ])
        ->name('account.jewel-bank.store');
    Route::delete('/account/jewel-bank/{webStorageItem}', [JewelBankController::class, 'destroy'])
        ->middleware([
            'module.enabled:'.ServerModule::JewelBank->value,
            'throttle:jewel-bank',
        ])
        ->name('account.jewel-bank.destroy');
    Route::get('/account/zen-bank', [ZenBankController::class, 'index'])
        ->middleware('module.enabled:'.ServerModule::ZenBank->value)
        ->name('account.zen-bank.index');
    Route::post('/account/zen-bank', [ZenBankController::class, 'store'])
        ->middleware([
            'module.enabled:'.ServerModule::ZenBank->value,
            'throttle:zen-bank',
        ])
        ->name('account.zen-bank.store');
    Route::post('/market/listings', [MarketListingController::class, 'store'])
        ->middleware([
            'module.enabled:'.ServerModule::Market->value,
            'throttle:market-listing',
        ])
        ->name('market.listings.store');
    Route::delete('/market/listings/{marketListing}', [MarketListingController::class, 'destroy'])
        ->middleware([
            'module.enabled:'.ServerModule::Market->value,
            'throttle:market-listing',
        ])
        ->name('market.listings.destroy');
    Route::post('/market/listings/{marketListing}/purchase', MarketPurchaseController::class)
        ->middleware([
            'module.enabled:'.ServerModule::Market->value,
            'throttle:market-purchase',
        ])
        ->name('market.listings.purchase');
    Route::post('/market/listings/{marketListing}/bids', AuctionBidController::class)
        ->middleware([
            'module.enabled:'.ServerModule::Market->value,
            'module.enabled:'.ServerModule::Auction->value,
            'throttle:auction-bid',
        ])
        ->name('market.listings.bids.store');
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
        Route::get('/audit-log', AdminAuditLogController::class)
            ->middleware('admin.permission:'.AdministratorPermission::ViewAuditLog->value)
            ->name('audit-log');
        Route::get('/moderation', [ModerationController::class, 'index'])
            ->name('moderation');
        Route::get('/economy/wallet', [WalletController::class, 'index'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageEconomy->value)
            ->name('wallet.index');
        Route::post('/economy/wallet', [WalletController::class, 'store'])
            ->middleware([
                'admin.permission:'.AdministratorPermission::ManageEconomy->value,
                'throttle:admin-wallet',
            ])
            ->name('wallet.store');
        Route::middleware('admin.permission:'.AdministratorPermission::ManageEconomy->value)
            ->group(function (): void {
                Route::get('/economy/vip-plans', [VipPlanController::class, 'index'])
                    ->name('vip-plans.index');
                Route::post('/economy/vip-plans', [VipPlanController::class, 'store'])
                    ->name('vip-plans.store');
                Route::put('/economy/vip-plans/{vipPlan}', [VipPlanController::class, 'update'])
                    ->name('vip-plans.update');
                Route::delete('/economy/vip-plans/{vipPlan}', [VipPlanController::class, 'destroy'])
                    ->name('vip-plans.destroy');
                Route::get('/economy/votes', [VoteSiteController::class, 'index'])
                    ->name('vote-sites.index');
                Route::post('/economy/votes', [VoteSiteController::class, 'store'])
                    ->name('vote-sites.store');
                Route::put('/economy/votes/{voteSite}', [VoteSiteController::class, 'update'])
                    ->name('vote-sites.update');
                Route::delete('/economy/votes/{voteSite}', [VoteSiteController::class, 'destroy'])
                    ->name('vote-sites.destroy');
                Route::post('/economy/vote-claims/{voteClaim}/review', VoteClaimReviewController::class)
                    ->middleware('throttle:admin-vote-review')
                    ->name('vote-claims.review');
                Route::get('/economy/market', [AdminMarketController::class, 'index'])
                    ->name('market.index');
                Route::post('/economy/market/{marketListing}/retry', [AdminMarketController::class, 'retry'])
                    ->middleware('throttle:admin-market')
                    ->name('market.retry');
                Route::get('/economy/recovery', [EconomyRecoveryController::class, 'index'])
                    ->name('economy-recovery.index');
                Route::post('/economy/recovery/storage/{webStorageItem}', [EconomyRecoveryController::class, 'retryStorage'])
                    ->middleware('throttle:admin-economy-recovery')
                    ->name('economy-recovery.storage.retry');
                Route::post('/economy/recovery/zen/{zenBankOperation}', [EconomyRecoveryController::class, 'retryZen'])
                    ->middleware('throttle:admin-economy-recovery')
                    ->name('economy-recovery.zen.retry');
                Route::post('/economy/market/{marketListing}/return', [AdminMarketController::class, 'returnItem'])
                    ->middleware('throttle:admin-market')
                    ->name('market.return');
            });
        Route::post('/moderation/accounts', [ModerationController::class, 'account'])
            ->middleware([
                'admin.permission:'.AdministratorPermission::ModerateAccounts->value,
                'throttle:admin-moderation',
            ])
            ->name('moderation.accounts');
        Route::post('/moderation/characters', [ModerationController::class, 'character'])
            ->middleware([
                'admin.permission:'.AdministratorPermission::ModerateCharacters->value,
                'throttle:admin-moderation',
            ])
            ->name('moderation.characters');
        Route::middleware('admin.permission:'.AdministratorPermission::ManageContent->value)
            ->group(function (): void {
                Route::get('/content/news', [AdminNewsPostController::class, 'index'])
                    ->name('news-posts.index');
                Route::post('/content/news', [AdminNewsPostController::class, 'store'])
                    ->name('news-posts.store');
                Route::put('/content/news/{newsPost}', [AdminNewsPostController::class, 'update'])
                    ->name('news-posts.update');
                Route::delete('/content/news/{newsPost}', [AdminNewsPostController::class, 'destroy'])
                    ->name('news-posts.destroy');
                Route::get('/content/downloads', [AdminDownloadController::class, 'index'])
                    ->name('downloads.index');
                Route::post('/content/downloads', [AdminDownloadController::class, 'store'])
                    ->name('downloads.store');
                Route::put('/content/downloads/{download}', [AdminDownloadController::class, 'update'])
                    ->name('downloads.update');
                Route::delete('/content/downloads/{download}', [AdminDownloadController::class, 'destroy'])
                    ->name('downloads.destroy');
            });
        Route::middleware('admin.full')->group(function (): void {
            Route::get('/game-masters', [GameMasterController::class, 'index'])
                ->name('game-masters.index');
            Route::post('/game-masters', [GameMasterController::class, 'store'])
                ->name('game-masters.store');
            Route::put('/game-masters/{gameMaster}', [GameMasterController::class, 'update'])
                ->name('game-masters.update');
        });
        Route::put('/reset-profile', [ResetProfileController::class, 'update'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('reset-profile.update');
        Route::get('/modules', [ModuleProfileController::class, 'index'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('modules.index');
        Route::put('/modules', [ModuleProfileController::class, 'update'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('modules.update');
        Route::get('/event-schedules', [EventScheduleController::class, 'index'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('event-schedules.index');
        Route::post('/event-schedules', [EventScheduleController::class, 'store'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('event-schedules.store');
        Route::put('/event-schedules/{eventScheduleEntry}', [EventScheduleController::class, 'update'])
            ->middleware('admin.permission:'.AdministratorPermission::ManageSettings->value)
            ->name('event-schedules.update');
        Route::get('/item-grants', [ItemGrantController::class, 'index'])
            ->middleware('admin.permission:'.AdministratorPermission::GrantItems->value)
            ->name('item-grants.index');
        Route::post('/item-grants', [ItemGrantController::class, 'store'])
            ->middleware([
                'admin.permission:'.AdministratorPermission::GrantItems->value,
                'throttle:admin-item-grant',
            ])
            ->name('item-grants.store');
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});

Route::post('/locale/{locale}', LocaleController::class)
    ->whereIn('locale', config('localization.supported'))
    ->name('locale.update');
