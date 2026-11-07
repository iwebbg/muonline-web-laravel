<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\AccountPanelConfiguration;
use App\Http\Controllers\Controller;
use App\Models\AccountWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays the account-owned custom item builder and trusted live quote inputs.
 */
final class ItemGeneratorController extends Controller
{
    /**
     * Displays the version-specific catalog and option limits.
     */
    public function __invoke(
        Request $request,
        ItemGeneratorCatalog $catalog,
        ItemGrantRules $rules,
        AccountPanelConfiguration $configuration,
        GameVersionProfile $versionProfile,
    ): View {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);
        abort_unless($versionProfile->capabilities()->itemGenerator, 404);

        $itemsByCode = [];

        foreach ($catalog->items() as $item) {
            $itemsByCode[(string) $item['code']] = $item;
        }

        $balance = AccountWallet::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->value('balance') ?? 0;

        return view('account.item-generator', [
            'itemsByCode' => $itemsByCode,
            'itemLevels' => $rules->itemLevels(),
            'optionLevels' => $rules->optionLevels(),
            'itemOptionLabels' => $configuration->itemOptionLabels(),
            'priceModifiers' => $catalog->priceModifiers(),
            'balance' => (int) $balance,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }
}
