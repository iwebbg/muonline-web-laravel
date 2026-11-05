<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ModerationRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateAccountRequest;
use App\Http\Requests\Admin\ModerateCharacterRequest;
use App\Models\Administrator;
use App\Moderation\Actions\ModerateGameTarget;
use App\Moderation\Exceptions\ModerationRejected;
use App\Moderation\ModerationResult;
use App\Moderation\ModerationTarget;
use App\Moderation\ModerationTargetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Presents and executes permission-scoped game account and character moderation.
 */
final class ModerationController extends Controller
{
    private const int RESULTS_PER_PAGE = 15;

    /**
     * Displays searchable non-sensitive game targets for the current administrator.
     */
    public function index(
        Request $request,
        ModerationRepository $moderationRepository,
    ): View {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $canModerateAccounts = $administrator->hasPermission(
            AdministratorPermission::ModerateAccounts,
        );
        $canModerateCharacters = $administrator->hasPermission(
            AdministratorPermission::ModerateCharacters,
        );

        abort_unless($canModerateAccounts || $canModerateCharacters, 403);

        $searchValue = $request->string('search')->trim()->toString();
        $search = $searchValue === '' ? null : mb_substr($searchValue, 0, 50);
        $currentPage = max(1, $request->integer('page', 1));
        $accountTargets = $moderationRepository->searchAccountGroups(
            $search,
            self::RESULTS_PER_PAGE + 1,
            ($currentPage - 1) * self::RESULTS_PER_PAGE,
        );
        $accountPaginator = $this->paginateTargets(
            $accountTargets,
            $request,
            'page',
            $currentPage,
            'moderation-targets',
        );
        $visibleAccountIds = collect($accountPaginator->items())
            ->pluck('accountId')
            ->all();
        $characterTargets = $canModerateCharacters
            ? $moderationRepository->charactersForAccounts($visibleAccountIds)
            : [];
        $characterGroups = collect($this->serializeTargets($characterTargets))
            ->groupBy('accountId')
            ->map(static fn (Collection $targets): array => $targets->values()->all())
            ->all();

        return view('admin.moderation', [
            'search' => $search ?? '',
            'accountTargets' => $accountPaginator,
            'characterGroups' => $characterGroups,
            'protectedControlCodeLabels' => [
                8 => __('ui.admin.moderation_game_master'),
                32 => __('ui.admin.moderation_game_master'),
            ],
            'canModerateAccounts' => $canModerateAccounts,
            'canModerateCharacters' => $canModerateCharacters,
            'writesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Executes one account block or unblock operation with an immutable audit row.
     */
    public function account(
        ModerateAccountRequest $request,
        ModerateGameTarget $moderateGameTarget,
    ): JsonResponse|RedirectResponse {
        return $this->execute(
            request: $request,
            moderateGameTarget: $moderateGameTarget,
            targetType: ModerationTargetType::Account,
            targetIdentifier: $request->targetIdentifier(),
        );
    }

    /**
     * Executes one offline character block or unblock with an immutable audit row.
     */
    public function character(
        ModerateCharacterRequest $request,
        ModerateGameTarget $moderateGameTarget,
    ): JsonResponse|RedirectResponse {
        return $this->execute(
            request: $request,
            moderateGameTarget: $moderateGameTarget,
            targetType: ModerationTargetType::Character,
            targetIdentifier: $request->targetIdentifier(),
        );
    }

    /**
     * Runs the shared action and converts safe domain failures to endpoint responses.
     */
    private function execute(
        ModerateAccountRequest|ModerateCharacterRequest $request,
        ModerateGameTarget $moderateGameTarget,
        ModerationTargetType $targetType,
        string $targetIdentifier,
    ): JsonResponse|RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            $result = $moderateGameTarget->handle(
                administrator: $administrator,
                targetType: $targetType,
                targetIdentifier: $targetIdentifier,
                action: $request->moderationAction(),
                reason: $request->moderationReason(),
                idempotencyKey: $request->idempotencyKey(),
            );
        } catch (ModerationRejected $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('ui.admin.moderation_rejected'),
                    'reason' => $exception->reason->value,
                ], $exception->httpStatus);
            }

            return back()
                ->withInput($request->safe()->except(['idempotencyKey']))
                ->withErrors([
                    'moderation' => __('ui.admin.moderation_reasons.'.$exception->reason->value),
                ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.admin.moderation_completed'),
                'result' => $this->serializeResult($result),
            ]);
        }

        return back()->with('status', __('ui.admin.moderation_completed'));
    }

    /**
     * Serializes moderation targets for a credential-free administrator view.
     *
     * @param  list<ModerationTarget>  $targets
     * @return list<array{type: string, identifier: string, accountId: string, blocked: bool, online: bool, controlCode: int}>
     */
    private function serializeTargets(array $targets): array
    {
        return array_map(
            static fn (ModerationTarget $target): array => $target->toArray(),
            $targets,
        );
    }

    /**
     * Builds one independent simple paginator while preserving the other result filters.
     *
     * @param  list<ModerationTarget>  $targets
     * @return Paginator<int, array{type: string, identifier: string, accountId: string, blocked: bool, online: bool, controlCode: int}>
     */
    private function paginateTargets(
        array $targets,
        Request $request,
        string $pageName,
        int $currentPage,
        string $fragment,
    ): Paginator {
        $paginator = new Paginator(
            $this->serializeTargets($targets),
            self::RESULTS_PER_PAGE,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ],
        );

        return $paginator
            ->appends($request->except($pageName))
            ->fragment($fragment);
    }

    /**
     * Serializes a committed result for interactive administrator clients.
     *
     * @return array{targetType: string, targetIdentifier: string, accountId: string, beforeBlocked: bool, afterBlocked: bool, beforeControlCode: int, afterControlCode: int}
     */
    private function serializeResult(ModerationResult $result): array
    {
        return [
            'targetType' => $result->targetType->value,
            'targetIdentifier' => $result->targetIdentifier,
            'accountId' => $result->accountId,
            'beforeBlocked' => $result->beforeBlocked,
            'afterBlocked' => $result->afterBlocked,
            'beforeControlCode' => $result->beforeControlCode,
            'afterControlCode' => $result->afterControlCode,
        ];
    }
}
