<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterStatResetRequest;
use App\StatReset\Actions\ExecuteCharacterStatReset;
use App\StatReset\Exceptions\CharacterStatResetRejected;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed stat reset and returns its audited outcome.
 */
final class CharacterStatResetController extends Controller
{
    /**
     * Applies one ownership-scoped stat reset for the authenticated account.
     */
    public function __invoke(
        ExecuteCharacterStatResetRequest $request,
        ExecuteCharacterStatReset $executeCharacterStatReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterStatReset->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (CharacterStatResetRejected $exception) {
            return response()->json([
                'message' => __('ui.account.stat_reset_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.stat_reset_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
