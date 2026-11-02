<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\GrandReset\Actions\ExecuteCharacterGrandReset;
use App\GrandReset\Exceptions\CharacterGrandResetRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterGrandResetRequest;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed grand reset and returns its audited outcome.
 */
final class CharacterGrandResetController extends Controller
{
    /**
     * Applies one ownership-scoped grand reset for the authenticated account.
     */
    public function __invoke(
        ExecuteCharacterGrandResetRequest $request,
        ExecuteCharacterGrandReset $executeCharacterGrandReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterGrandReset->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (CharacterGrandResetRejected $exception) {
            return response()->json([
                'message' => __('ui.account.grand_reset_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.grand_reset_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
