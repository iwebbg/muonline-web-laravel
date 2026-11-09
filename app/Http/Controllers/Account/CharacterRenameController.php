<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RenameCharacterRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases a new name for one authenticated account character.
 */
final class CharacterRenameController extends Controller
{
    /**
     * Applies one ownership-scoped, idempotent rename.
     */
    public function __invoke(
        RenameCharacterRequest $request,
        ExecuteCharacterService $executeCharacterService,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $operation = $executeCharacterService->rename(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                newName: $request->string('new_name')->toString(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (CharacterServiceRejected $exception) {
            return back()->withInput()->withErrors([
                'character_service' => __('ui.account.character_service_errors.'.$exception->reason->value),
            ]);
        }

        return redirect()->route('account.dashboard', [
            'tab' => 'services',
            'character' => $operation->requested_name,
        ])->with('accountStatus', __('ui.account.character_rename_completed'));
    }
}
