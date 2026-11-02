<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterPkClearRequest;
use App\PkClear\Actions\ExecuteCharacterPkClear;
use App\PkClear\Exceptions\CharacterPkClearRejected;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed PK clear and returns its audited outcome.
 */
final class CharacterPkClearController extends Controller
{
    /**
     * Applies one ownership-scoped PK clear for the authenticated account.
     */
    public function __invoke(
        ExecuteCharacterPkClearRequest $request,
        ExecuteCharacterPkClear $executeCharacterPkClear,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterPkClear->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (CharacterPkClearRejected $exception) {
            return response()->json([
                'message' => __('ui.account.pk_clear_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.pk_clear_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
