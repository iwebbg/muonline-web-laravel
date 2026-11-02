<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterResetRequest;
use App\Reset\Actions\ExecuteCharacterReset;
use App\Reset\Exceptions\CharacterResetRejected;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed character reset and returns its audited outcome.
 */
final class CharacterResetController extends Controller
{
    /**
     * Applies one ownership-scoped reset for the authenticated game account.
     */
    public function __invoke(
        ExecuteCharacterResetRequest $request,
        ExecuteCharacterReset $executeCharacterReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterReset->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (CharacterResetRejected $exception) {
            return response()->json([
                'message' => __('ui.account.reset_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.reset_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
