<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CharacterMove\Actions\PreviewMoveToTown;
use App\Game\Contracts\CharacterMoveRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewMoveToTownRequest;
use App\Reset\ResetProfileReader;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped Move to Town preview without changing game data.
 */
final class MoveToTownPreviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        PreviewMoveToTownRequest $request,
        CharacterMoveRepository $characterMoveRepository,
        ResetProfileReader $resetProfileReader,
        PreviewMoveToTown $previewMoveToTown,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $characterMoveRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewMoveToTown->handle(
            character: $character,
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
