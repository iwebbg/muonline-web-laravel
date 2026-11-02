<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewCharacterStatResetRequest;
use App\Reset\ResetProfileReader;
use App\StatReset\Actions\PreviewCharacterStatReset;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped stat-reset preview without changing game data.
 */
final class StatResetPreviewController extends Controller
{
    /**
     * Calculates the current stat-reset outcome for one authenticated character.
     */
    public function __invoke(
        PreviewCharacterStatResetRequest $request,
        AccountReadRepository $accountReadRepository,
        ResetProfileReader $resetProfileReader,
        PreviewCharacterStatReset $previewCharacterStatReset,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $accountReadRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewCharacterStatReset->handle(
            character: $character,
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
