<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CharacterMove\Actions\ExecuteCharacterMove;
use App\CharacterMove\Exceptions\CharacterMoveRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterMoveRequest;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed Move to Town and returns its audited outcome.
 */
final class CharacterMoveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ExecuteCharacterMoveRequest $request,
        ExecuteCharacterMove $executeCharacterMove,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterMove->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (CharacterMoveRejected $exception) {
            return response()->json([
                'message' => __('ui.account.move_to_town_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.move_to_town_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
