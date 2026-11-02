<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewCharacterResetRequest;
use App\Reset\Actions\PreviewCharacterReset;
use App\Reset\ResetProfileReader;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped reset preview without changing game state.
 */
final class ResetPreviewController extends Controller
{
    /**
     * Calculates the current reset outcome for one authenticated account character.
     */
    public function __invoke(
        PreviewCharacterResetRequest $request,
        AccountReadRepository $accountReadRepository,
        ResetProfileReader $resetProfileReader,
        PreviewCharacterReset $previewCharacterReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $accountReadRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewCharacterReset->handle(
            character: $character,
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
