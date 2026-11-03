<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\ItemStorageRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ReadItemStorageRequest;
use Illuminate\Http\JsonResponse;

/**
 * Returns decoded read-only item storage for an owned character and account.
 */
final class ItemStorageController extends Controller
{
    /**
     * Creates the endpoint with the version-aware item storage reader.
     */
    public function __construct(
        private readonly ItemStorageRepository $itemStorageRepository,
    ) {}

    /**
     * Returns item data only after the repository repeats ownership scoping.
     */
    public function __invoke(ReadItemStorageRequest $request): JsonResponse
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characterName = $request->string('characterName')->toString();
        $storage = $characterName === ''
            ? $this->itemStorageRepository->forAccount($account->username)
            : $this->itemStorageRepository->forAccountAndCharacter(
                $account->username,
                $characterName,
            );

        abort_if($storage === null, 404);

        return response()->json([
            'storage' => $storage->toArray(),
        ]);
    }
}
