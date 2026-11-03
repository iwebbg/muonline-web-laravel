<?php

namespace App\Providers;

use App\Auth\GameAccountUserProvider;
use App\Game\Clocks\SqlServerRealmClock;
use App\Game\Clocks\SystemRealmClock;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\Contracts\CharacterResetRepository;
use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Contracts\CharacterVipRepository;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Contracts\ModerationRepository;
use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Repositories\LegacyGameAccountLookupRepository;
use App\Game\Repositories\SqlServer97d99iAccountCredentialRepository;
use App\Game\Repositories\SqlServer97d99iAccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServer97d99iAccountReadRepository;
use App\Game\Repositories\SqlServer97d99iAccountRegistrationRepository;
use App\Game\Repositories\SqlServer97d99iCharacterGrandResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterMoveRepository;
use App\Game\Repositories\SqlServer97d99iCharacterPkClearRepository;
use App\Game\Repositories\SqlServer97d99iCharacterResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatAllocationRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterVipRepository;
use App\Game\Repositories\SqlServer97d99iItemSerialRepository;
use App\Game\Repositories\SqlServer97d99iItemStorageRepository;
use App\Game\Repositories\SqlServer97d99iMarketItemEscrowRepository;
use App\Game\Repositories\SqlServer97d99iModerationRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use App\Game\Repositories\SqlServer97d99iZenBankRepository;
use App\Game\Status\FixtureGameServerStatus;
use App\Game\Status\TcpGameServerStatus;
use App\Modules\ModuleManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as LaravelView;

/**
 * Registers version-aware game adapters, module settings and request limiters.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $repositoryClass = config('game.data_source') === 'database'
            ? SqlServer97d99iRealmReadRepository::class
            : FixtureRealmReadRepository::class;
        $clockClass = config('game.data_source') === 'database'
            ? SqlServerRealmClock::class
            : SystemRealmClock::class;
        $serverStatusClass = config('game.data_source') === 'database'
            ? TcpGameServerStatus::class
            : FixtureGameServerStatus::class;

        $this->app->bind(RealmReadRepository::class, $repositoryClass);
        $this->app->bind(RealmClock::class, $clockClass);
        $this->app->singleton(GameServerStatus::class, $serverStatusClass);
        $this->app->bind(AccountCredentialRepository::class, SqlServer97d99iAccountCredentialRepository::class);
        $this->app->bind(AccountPasswordRecoveryRepository::class, SqlServer97d99iAccountPasswordRecoveryRepository::class);
        $this->app->bind(AccountRegistrationRepository::class, SqlServer97d99iAccountRegistrationRepository::class);
        $this->app->bind(AccountReadRepository::class, SqlServer97d99iAccountReadRepository::class);
        $this->app->bind(CharacterResetRepository::class, SqlServer97d99iCharacterResetRepository::class);
        $this->app->bind(CharacterGrandResetRepository::class, SqlServer97d99iCharacterGrandResetRepository::class);
        $this->app->bind(CharacterMoveRepository::class, SqlServer97d99iCharacterMoveRepository::class);
        $this->app->bind(CharacterPkClearRepository::class, SqlServer97d99iCharacterPkClearRepository::class);
        $this->app->bind(CharacterStatAllocationRepository::class, SqlServer97d99iCharacterStatAllocationRepository::class);
        $this->app->bind(CharacterStatResetRepository::class, SqlServer97d99iCharacterStatResetRepository::class);
        $this->app->bind(CharacterVipRepository::class, SqlServer97d99iCharacterVipRepository::class);
        $this->app->bind(ItemStorageRepository::class, SqlServer97d99iItemStorageRepository::class);
        $this->app->bind(ItemEscrowRepository::class, SqlServer97d99iMarketItemEscrowRepository::class);
        $this->app->bind(ItemSerialRepository::class, SqlServer97d99iItemSerialRepository::class);
        $this->app->bind(MarketItemEscrowRepository::class, SqlServer97d99iMarketItemEscrowRepository::class);
        $this->app->bind(ModerationRepository::class, SqlServer97d99iModerationRepository::class);
        $this->app->bind(ZenBankRepository::class, SqlServer97d99iZenBankRepository::class);
        $this->app->bind(GameAccountLookupRepository::class, LegacyGameAccountLookupRepository::class);
        $this->app->singleton(ModuleManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(
            ['layouts.app', 'home', 'auth.login'],
            function (LaravelView $view): void {
                $gameServerStatus = $this->app->make(GameServerStatus::class);

                $view->with('gameServerOnline', $gameServerStatus->isOnline());
            },
        );

        Auth::provider('game_accounts', function (Application $application): GameAccountUserProvider {
            return new GameAccountUserProvider($application->make(DatabaseManager::class));
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $username = Str::lower($request->string('username')->toString());
            $limiterKey = Str::transliterate($username.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('registration', function (Request $request): Limit {
            $limiterKey = Str::transliterate('registration|'.$request->ip());

            return Limit::perHour(10)->by($limiterKey);
        });

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());
            $limiterKey = Str::transliterate($email.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('admin-wallet', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(20)->by($limiterKey);
        });

        RateLimiter::for('vip-purchase', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('vote-claim', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('admin-vote-review', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('market-listing', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('market-purchase', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('auction-bid', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(15)->by($limiterKey);
        });

        RateLimiter::for('web-storage', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('jewel-bank', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('zen-bank', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('admin-market', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(20)->by($limiterKey);
        });

        RateLimiter::for('admin-economy-recovery', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(20)->by($limiterKey);
        });

        RateLimiter::for('admin-item-grant', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('reset-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('character-reset', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('grand-reset-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('grand-reset', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('stat-allocation-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('stat-allocation', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('stat-reset-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('stat-reset', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('pk-clear-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('pk-clear', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('move-to-town-preview', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('move-to-town', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('item-storage', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(30)->by($limiterKey);
        });

        RateLimiter::for('account-credentials', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('admin-moderation', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(20)->by($limiterKey);
        });

        RateLimiter::for('password-recovery-link', function (Request $request): Limit {
            $accountId = Str::lower($request->string('account')->toString());
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perHour(5)->by($limiterKey);
        });

        RateLimiter::for('password-recovery-reset', function (Request $request): Limit {
            $accountId = Str::lower($request->string('account')->toString());
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });
    }
}
