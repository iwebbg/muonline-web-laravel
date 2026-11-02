<?php

namespace App\Http\Controllers\Admin;

use App\Game\Contracts\ModerationRepository;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreItemGrantRequest;
use App\ItemGrants\Actions\GrantItemToWarehouse;
use App\ItemGrants\Exceptions\ItemGrantRejected;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays and executes permission-scoped warehouse item grants.
 */
final class ItemGrantController extends Controller
{
    /**
     * Displays verified items, searchable accounts and recent grant history.
     */
    public function index(
        Request $request,
        ModerationRepository $moderationRepository,
        ItemCatalog97d99i $catalog,
    ): View {
        $searchValue = $request->string('search')->trim()->toString();
        $search = $searchValue === ''
            ? null
            : mb_substr($searchValue, 0, 50);
        $definitions = [];

        foreach ($catalog->allDefinitions() as $definition) {
            $definitions[] = [
                'code' => $definition->code(),
                'name' => $definition->name,
                'image' => $definition->image,
                'width' => $definition->width,
                'height' => $definition->height,
            ];
        }
        $definitionsByCode = [];

        foreach ($definitions as $definition) {
            $definitionsByCode[$definition['code']] = $definition;
        }

        return view('admin.item-grants', [
            'search' => $search ?? '',
            'accountTargets' => $moderationRepository->searchAccounts($search, 25),
            'definitions' => $definitions,
            'definitionsByCode' => $definitionsByCode,
            'recentOperations' => ItemGrantOperation::query()
                ->latest('id')
                ->limit(30)
                ->get(),
            'idempotencyKey' => (string) Str::uuid(),
            'writesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Delivers one validated item or returns a safe operator-facing error.
     */
    public function store(
        StoreItemGrantRequest $request,
        GrantItemToWarehouse $grantItemToWarehouse,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            $result = $grantItemToWarehouse->execute(
                administrator: $administrator,
                accountId: $request->string('account_id')->toString(),
                options: $request->itemOptions(),
                reason: $request->string('reason')->trim()->toString(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (ItemGrantRejected $exception) {
            return back()
                ->withInput($request->safe()->except(['idempotency_key']))
                ->withErrors([
                    'item_grant' => __('ui.admin.item_grant_reasons.'.$exception->reason->value),
                ]);
        }

        return redirect()
            ->route('admin.item-grants.index', ['search' => $result->accountId])
            ->with('status', __('ui.admin.item_grant_completed', [
                'item' => $result->itemName,
                'account' => $result->accountId,
            ]));
    }
}
