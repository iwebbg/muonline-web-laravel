<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\GrandReset\Actions\PreviewCharacterGrandReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewCharacterGrandResetRequest;
use App\Reset\ResetProfileReader;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped grand-reset preview without changing game data.
 */
final class GrandResetPreviewController extends Controller
{
    /**
     * Calculates the current grand-reset outcome for one authenticated character.
     */
    public function __invoke(
        PreviewCharacterGrandResetRequest $request,
        AccountReadRepository $accountReadRepository,
        ResetProfileReader $resetProfileReader,
        PreviewCharacterGrandReset $previewCharacterGrandReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $accountReadRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewCharacterGrandReset->handle(
            character: $character,
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
