<?php

namespace App\Providers;

use App\Auth\GameAccountUserProvider;
use App\Game\Clocks\SqlServerRealmClock;
use App\Game\Clocks\SystemRealmClock;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Repositories\LegacyGameAccountLookupRepository;
use App\Game\Status\FixtureGameServerStatus;
use App\Game\Status\TcpGameServerStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Modules\ModuleManager;
use App\Website\WebsiteSettings;
use App\Website\WebsiteSettingsManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as LaravelView;
use LogicException;
use Throwable;

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
        $profileKey = (string) config('game.profile.key');
        $profileClass = config('game.version_profiles.'.$profileKey);

        if (! is_string($profileClass) || ! is_subclass_of($profileClass, GameVersionProfile::class)) {
            throw new LogicException(sprintf(
                'Unsupported game version profile [%s].',
                $profileKey,
            ));
        }

        $this->app->singleton(GameVersionProfile::class, $profileClass);

        $versionProfile = $this->app->make(GameVersionProfile::class);

        foreach ($versionProfile->repositoryBindings() as $contract => $adapter) {
            $this->app->bind($contract, $adapter);
        }

        if (config('game.data_source') !== 'database') {
            $this->app->bind(RealmReadRepository::class, FixtureRealmReadRepository::class);
        }

        $clockClass = config('game.data_source') === 'database'
            ? SqlServerRealmClock::class
            : SystemRealmClock::class;
        $serverStatusClass = config('game.data_source') === 'database'
            ? TcpGameServerStatus::class
            : FixtureGameServerStatus::class;

        $this->app->bind(RealmClock::class, $clockClass);
        $this->app->singleton(GameServerStatus::class, $serverStatusClass);
        $this->app->bind(GameAccountLookupRepository::class, LegacyGameAccountLookupRepository::class);
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(WebsiteSettingsManager::class);
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

                if (in_array($view->name(), ['layouts.app', 'home'], true)) {
                    $websiteSettings = $this->app->make(WebsiteSettingsManager::class)->current();
                    $view->with('websiteSettings', $websiteSettings);
                }

                if ($view->name() !== 'layouts.app') {
                    return;
                }

                $view->with(
                    'websiteLogoUrl',
                    $websiteSettings->logoPath === null
                        ? null
                        : Storage::disk('public')->url($websiteSettings->logoPath),
                );
                $view->with(
                    'websiteOgImageUrl',
                    $websiteSettings->ogImagePath === null
                        ? null
                        : Storage::disk('public')->url($websiteSettings->ogImagePath),
                );
            },
        );

        View::composer('errors.layout', function (LaravelView $view): void {
            $websiteSettings = $this->errorPageWebsiteSettings();

            $view->with('websiteSettings', $websiteSettings);
            $view->with(
                'websiteLogoUrl',
                $websiteSettings->logoPath === null
                    ? null
                    : Storage::disk('public')->url($websiteSettings->logoPath),
            );
        });

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

        RateLimiter::for('account-activation-link', function (Request $request): Limit {
            $accountId = Str::lower($request->string('account')->toString());
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perHour(5)->by($limiterKey);
        });

        RateLimiter::for('account-deletion', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perHour(5)->by($limiterKey);
        });

        RateLimiter::for('server-status', function (Request $request): Limit {
            return Limit::perMinute(60)->by('server-status|'.$request->ip());
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

        RateLimiter::for('shop-purchase', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(10)->by($limiterKey);
        });

        RateLimiter::for('shop-reward', function (Request $request): Limit {
            $accountId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($accountId.'|'.$request->ip());

            return Limit::perMinute(15)->by($limiterKey);
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

        RateLimiter::for('admin-item-grant-search', function (Request $request): Limit {
            $administratorId = (string) ($request->user('admin')?->getAuthIdentifier() ?? 'guest');
            $limiterKey = Str::transliterate($administratorId.'|'.$request->ip());

            return Limit::perMinute(60)->by($limiterKey);
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

    /**
     * Resolves managed identity without allowing a database failure to break an error page.
     */
    private function errorPageWebsiteSettings(): WebsiteSettings
    {
        try {
            return $this->app->make(WebsiteSettingsManager::class)->current();
        } catch (Throwable) {
            return WebsiteSettings::defaults();
        }
    }
}
