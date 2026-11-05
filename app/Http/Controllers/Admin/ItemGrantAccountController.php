<?php

namespace App\Http\Controllers\Admin;

use App\Game\Contracts\ModerationRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchItemGrantAccountRequest;
use App\Moderation\ModerationTarget;
use Illuminate\Http\JsonResponse;

/**
 * Supplies bounded account suggestions for the administrator item-grant form.
 */
final class ItemGrantAccountController extends Controller
{
    /**
     * Returns non-sensitive matching account identifiers and online states.
     */
    public function __invoke(
        SearchItemGrantAccountRequest $request,
        ModerationRepository $moderationRepository,
    ): JsonResponse {
        $accounts = array_map(
            static fn (ModerationTarget $target): array => [
                'account_id' => $target->accountId,
                'online' => $target->online,
            ],
            $moderationRepository->searchAccounts($request->search(), 10),
        );

        return response()->json(['data' => $accounts]);
    }
}
