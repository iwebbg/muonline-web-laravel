<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\CharacterServices\CharacterClassChangeResolver;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\AccountPanelConfiguration;
use App\Models\AccountWallet;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\VipPurchase;
use App\Models\WebStorageItem;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use App\Shop\EconomySettingReader;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays private character data for the authenticated MuOnline account.
 */
final class AccountDashboardController extends Controller
{
    /**
     * Creates the controller with the version-aware private account reader.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ModuleManager $moduleManager,
        private readonly AccountPanelConfiguration $accountPanelConfiguration,
        private readonly GameVersionProfile $versionProfile,
        private readonly CharacterClassChangeResolver $classChangeResolver,
        private readonly EconomySettingReader $economySettingReader,
    ) {}

    /**
     * Displays only characters owned by the current authenticated account.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characterEntries = $this->accountReadRepository->charactersFor($account->username);
        $characters = [];
        $characterNames = [];

        foreach ($characterEntries as $characterEntry) {
            $character = $characterEntry->toArray();
            $statDefinitions = [];

            foreach ($this->versionProfile->statsForClass($characterEntry->classCode) as $statDefinition) {
                $statDefinitions[] = $statDefinition->toClientArray(app()->getLocale());
            }

            $character['statDefinitions'] = $statDefinitions;
            $character['classChangeOptions'] = array_map(
                static fn (CharacterClassDefinition $definition): array => [
                    'family' => $definition->family,
                    'name' => $definition->name,
                    'classCode' => $definition->code,
                ],
                $this->classChangeResolver->optionsFor($characterEntry->classCode),
            );
            $characters[] = $character;
            $characterNames[] = $characterEntry->name;
        }

        $vipEnabled = $this->moduleManager->enabled(ServerModule::Vip)
            && $this->versionProfile->capabilities()->characterVip;
        $creditsEnabled = $this->moduleManager->enabled(ServerModule::Credits);
        $wallet = $vipEnabled || $creditsEnabled
            ? AccountWallet::query()
                ->where('account_id', $account->username)
                ->where('currency', WalletCurrency::Credits)
                ->first()
            : null;
        $vipPlanEntries = $vipEnabled
            ? VipPlan::query()
                ->available()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
        $vipPlans = [];

        foreach ($vipPlanEntries as $vipPlanEntry) {
            $vipPlans[] = [
                'id' => $vipPlanEntry->getKey(),
                'name' => $vipPlanEntry->localizedName(app()->getLocale()),
                'description' => $vipPlanEntry->localizedDescription(app()->getLocale()),
                'durationDays' => $vipPlanEntry->duration_days,
                'priceCredits' => $vipPlanEntry->price_credits,
            ];
        }

        $vipEntitlements = $vipEnabled
            ? VipEntitlement::query()
                ->where('account_id', $account->username)
                ->get()
                ->keyBy('character_name')
            : collect();
        $activeVipEntitlementCount = 0;

        foreach ($vipEntitlements as $vipEntitlement) {
            if ($vipEntitlement->expires_at->isFuture()) {
                $activeVipEntitlementCount++;
            }
        }

        $vipPurchases = $vipEnabled
            ? VipPurchase::query()
                ->where('account_id', $account->username)
                ->latest('id')
                ->limit(10)
                ->get()
            : collect();
        $gameWritesEnabled = (bool) config('game.writes_enabled', false)
            && ! (bool) config('database.connections.game.readonly', true);
        $economySettings = $this->economySettingReader->current();
        $characterServicesEnabled = $creditsEnabled
            && ($economySettings->characterRenameEnabled || $economySettings->characterClassChangeEnabled);
        $accountPanelConfiguration = $this->accountPanelConfiguration->toArray(
            writesEnabled: $gameWritesEnabled,
            csrfToken: $request->session()->token(),
        );
        $webStorageEnabled = $this->moduleManager->enabled(ServerModule::WebStorage);
        $marketEnabled = $this->moduleManager->enabled(ServerModule::Market);
        $auctionEnabled = $this->moduleManager->enabled(ServerModule::Auction);
        $webStorageItems = [];

        if ($webStorageEnabled && Schema::hasTable('web_storage_items')) {
            $storedItems = WebStorageItem::query()
                ->where('account_id', $account->username)
                ->where('storage_context', WebStorageContext::WebVault)
                ->where('status', WebStorageItemStatus::Available)
                ->latest('id')
                ->limit(100)
                ->get();

            foreach ($storedItems as $storedItem) {
                $webStorageItems[] = [
                    'publicId' => $storedItem->public_id,
                    'item' => $storedItem->item_snapshot,
                    'withdrawUrl' => route('account.web-storage.destroy', $storedItem),
                ];
            }
        }

        $allowedSections = [
            'overview',
            'inventory',
            'stats',
            'stat-reset',
            'pk-clear',
            'move-to-town',
            'reset',
            'grand-reset',
            'vaults',
        ];

        if ($characterServicesEnabled) {
            $allowedSections[] = 'services';
        }
        $requestedSection = $request->string('tab')->toString();
        $requestedCharacter = $request->string('character')->toString();
        $requestedCharacterService = $request->string('service')->toString();
        $initialSection = in_array($requestedSection, $allowedSections, true)
            ? $requestedSection
            : 'overview';
        $availableCharacterServices = [];

        if ($creditsEnabled && $economySettings->characterRenameEnabled) {
            $availableCharacterServices[] = 'rename';
        }

        if ($creditsEnabled && $economySettings->characterClassChangeEnabled) {
            $availableCharacterServices[] = 'class-change';
        }

        if ($characterNames === []) {
            $initialSection = 'vaults';
        }

        $accountPanelConfiguration['initialSection'] = $initialSection;
        $accountPanelConfiguration['initialCharacter'] = in_array($requestedCharacter, $characterNames, true)
            ? $requestedCharacter
            : ($characterNames[0] ?? null);
        $accountPanelConfiguration['initialCharacterService'] = in_array(
            $requestedCharacterService,
            $availableCharacterServices,
            true,
        ) ? $requestedCharacterService : null;
        $accountPanelConfiguration['initialCharacterRenameName'] = $request->old('new_name', '');
        $accountPanelConfiguration['initialCharacterClassFamily'] = $request->old('target_family', '');
        $accountPanelConfiguration['webStorageEnabled'] = $webStorageEnabled;
        $accountPanelConfiguration['marketEnabled'] = $marketEnabled;
        $accountPanelConfiguration['auctionEnabled'] = $auctionEnabled;
        $accountPanelConfiguration['webStorageStoreUrl'] = route('account.web-storage.store');
        $accountPanelConfiguration['marketListingStoreUrl'] = route('market.listings.store');
        $accountPanelConfiguration['webStorageItems'] = $webStorageItems;

        return view('account.dashboard', [
            'account' => $account,
            'characters' => $characters,
            'walletBalance' => $wallet?->balance ?? 0,
            'vipPlans' => $vipPlans,
            'vipEntitlements' => $vipEntitlements,
            'activeVipEntitlementCount' => $activeVipEntitlementCount,
            'vipPurchases' => $vipPurchases,
            'vipIdempotencyKey' => (string) Str::uuid(),
            'vipEnabled' => $vipEnabled,
            'gameWritesEnabled' => $gameWritesEnabled,
            'accountPanelConfiguration' => $accountPanelConfiguration,
            'economySettings' => $economySettings,
            'characterServicesEnabled' => $characterServicesEnabled,
            'characterRenameIdempotencyKey' => (string) Str::uuid(),
            'characterClassChangeIdempotencyKey' => (string) Str::uuid(),
        ]);
    }
}
