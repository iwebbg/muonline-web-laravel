<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseCharacterLevelUpPointsRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases unallocated LevelUpPoints for one authenticated account character.
 */
final class CharacterLevelUpPointsPurchaseController extends Controller
{
    /**
     * Applies one validated and idempotent paid LevelUpPoints package.
     */
    public function __invoke(
        PurchaseCharacterLevelUpPointsRequest $request,
        ExecuteCharacterService $executeCharacterService,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $executeCharacterService->purchaseLevelUpPoints(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                packageCount: $request->integer('package_count'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (CharacterServiceRejected $exception) {
            return back()->withInput()->withErrors([
                'character_service' => __('ui.account.character_service_errors.'.$exception->reason->value),
            ]);
        }

        return redirect()->route('account.dashboard', [
            'tab' => 'services',
            'character' => $request->string('character_name')->toString(),
        ])->with('accountStatus', __('ui.account.character_level_up_points_purchase_completed'));
    }
}
