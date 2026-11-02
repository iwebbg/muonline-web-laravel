<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\CharacterPkClearRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PreviewCharacterPkClearRequest;
use App\PkClear\Actions\PreviewCharacterPkClear;
use App\Reset\ResetProfileReader;
use Illuminate\Http\JsonResponse;

/**
 * Returns an ownership-scoped PK clear preview without changing game data.
 */
final class PkClearPreviewController extends Controller
{
    /**
     * Calculates the current PK clear outcome for one authenticated character.
     */
    public function __invoke(
        PreviewCharacterPkClearRequest $request,
        CharacterPkClearRepository $pkClearRepository,
        ResetProfileReader $resetProfileReader,
        PreviewCharacterPkClear $previewCharacterPkClear,
    ): JsonResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $character = $pkClearRepository->characterFor(
            accountId: $account->username,
            characterName: $request->characterName(),
        );

        abort_if($character === null, 404);

        $preview = $previewCharacterPkClear->handle(
            character: $character,
            settings: $resetProfileReader->current(),
        );

        return response()->json([
            'preview' => $preview->toClientArray(),
        ]);
    }
}
