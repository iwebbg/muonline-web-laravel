<?php

namespace App\Providers;

use App\Auth\GameAccountUserProvider;
use App\Game\Clocks\SqlServerRealmClock;
use App\Game\Clocks\SystemRealmClock;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterResetRepository;
use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Repositories\SqlServer97d99iAccountReadRepository;
use App\Game\Repositories\SqlServer97d99iCharacterGrandResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatAllocationRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatResetRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        $this->app->bind(RealmReadRepository::class, $repositoryClass);
        $this->app->bind(RealmClock::class, $clockClass);
        $this->app->bind(AccountReadRepository::class, SqlServer97d99iAccountReadRepository::class);
        $this->app->bind(CharacterResetRepository::class, SqlServer97d99iCharacterResetRepository::class);
        $this->app->bind(CharacterGrandResetRepository::class, SqlServer97d99iCharacterGrandResetRepository::class);
        $this->app->bind(CharacterStatAllocationRepository::class, SqlServer97d99iCharacterStatAllocationRepository::class);
        $this->app->bind(CharacterStatResetRepository::class, SqlServer97d99iCharacterStatResetRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('game_accounts', function (Application $application): GameAccountUserProvider {
            return new GameAccountUserProvider($application->make(DatabaseManager::class));
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $username = Str::lower($request->string('username')->toString());
            $limiterKey = Str::transliterate($username.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
        });

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());
            $limiterKey = Str::transliterate($email.'|'.$request->ip());

            return Limit::perMinute(5)->by($limiterKey);
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
    }
}
