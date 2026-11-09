<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangeCharacterClassRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases a class-family change for one authenticated account character.
 */
final class CharacterClassChangeController extends Controller
{
    /**
     * Applies one ownership-scoped, idempotent class change.
     */
    public function __invoke(
        ChangeCharacterClassRequest $request,
        ExecuteCharacterService $executeCharacterService,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $executeCharacterService->changeClass(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                targetFamily: $request->string('target_family')->toString(),
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
        ])->with('accountStatus', __('ui.account.character_class_change_completed'));
    }
}
