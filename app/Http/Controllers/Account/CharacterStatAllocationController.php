<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExecuteCharacterStatAllocationRequest;
use App\Stats\Actions\ExecuteCharacterStatAllocation;
use App\Stats\Exceptions\CharacterStatAllocationRejected;
use Illuminate\Http\JsonResponse;

/**
 * Executes a confirmed stat allocation and returns its audited outcome.
 */
final class CharacterStatAllocationController extends Controller
{
    /**
     * Applies one ownership-scoped point distribution for the authenticated account.
     */
    public function __invoke(
        ExecuteCharacterStatAllocationRequest $request,
        ExecuteCharacterStatAllocation $executeCharacterStatAllocation,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $result = $executeCharacterStatAllocation->handle(
                accountId: $account->username,
                characterName: $request->characterName(),
                idempotencyKey: $request->idempotencyKey(),
                allocation: $request->allocation(),
            );
        } catch (CharacterStatAllocationRejected $exception) {
            return response()->json([
                'message' => __('ui.account.stat_allocation_rejected'),
                'reason' => $exception->reason->value,
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => __('ui.account.stat_allocation_completed'),
            'result' => $result->toClientArray(),
        ]);
    }
}
