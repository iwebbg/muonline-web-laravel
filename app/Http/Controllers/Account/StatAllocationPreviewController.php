<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewCharacterStatAllocationRequest;
use App\Reset\ResetProfileReader;
use App\Stats\Actions\PreviewCharacterStatAllocation;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped stat allocation preview without changing game data.
 */
final class StatAllocationPreviewController extends Controller
{
    /**
     * Calculates the current allocation outcome for one authenticated character.
     */
    public function __invoke(
        PreviewCharacterStatAllocationRequest $request,
        AccountReadRepository $accountReadRepository,
        PreviewCharacterStatAllocation $previewCharacterStatAllocation,
        ResetProfileReader $resetProfileReader,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $accountReadRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewCharacterStatAllocation->handle(
            character: $character,
            allocation: $request->allocation(),
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
