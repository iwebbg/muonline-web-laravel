<?php

namespace App\Http\Controllers\Admin;

use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Http\AccountPanelConfiguration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterItemGrantHistoryRequest;
use App\Http\Requests\Admin\StoreItemGrantRequest;
use App\ItemGrants\Actions\GrantItemToWarehouse;
use App\ItemGrants\Exceptions\ItemGrantRejected;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays and executes permission-scoped warehouse item grants.
 */
final class ItemGrantController extends Controller
{
    /**
     * Displays verified items, searchable accounts and filterable grant history.
     */
    public function index(
        FilterItemGrantHistoryRequest $request,
        ItemCatalog $catalog,
        ItemGrantRules $itemGrantRules,
        AccountPanelConfiguration $accountPanelConfiguration,
    ): View {
        $requestedAccountId = $request->initialAccountId();
        $initialAccountId = preg_match('/\A[A-Za-z0-9_-]{4,10}\z/', $requestedAccountId) === 1
            ? $requestedAccountId
            : '';
        $historySearch = $request->historySearch();
        $historyStatus = $request->historyStatus();
        $definitions = [];

        foreach ($catalog->allDefinitions() as $definition) {
            $definitions[] = [
                'code' => $definition->code(),
                'name' => $definition->name,
                'image' => $definition->image,
                'width' => $definition->width,
                'height' => $definition->height,
                'excellentType' => $definition->excellentType,
                'optionType' => $definition->optionType,
            ];
        }
        $definitionsByCode = [];

        foreach ($definitions as $definition) {
            $definitionsByCode[$definition['code']] = $definition;
        }

        $operationsQuery = ItemGrantOperation::query();

        if ($historySearch !== '') {
            $searchPattern = '%'.$historySearch.'%';

            $operationsQuery->where(function (Builder $query) use ($historySearch, $searchPattern): void {
                $query
                    ->whereLike('account_id', $searchPattern)
                    ->orWhereLike('item_name', $searchPattern)
                    ->orWhereLike('reason', $searchPattern);

                if (ctype_digit($historySearch)) {
                    $numericSearch = (int) $historySearch;

                    $query
                        ->orWhere('id', $numericSearch)
                        ->orWhere('serial', $numericSearch);
                }
            });
        }

        if ($historyStatus !== null) {
            $operationsQuery->where('status', $historyStatus->value);
        }

        $operations = $operationsQuery
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.item-grants', [
            'initialAccountId' => $initialAccountId,
            'definitions' => $definitions,
            'definitionsByCode' => $definitionsByCode,
            'itemLevels' => $itemGrantRules->itemLevels(),
            'optionLevels' => $itemGrantRules->optionLevels(),
            'itemOptionLabels' => $accountPanelConfiguration->itemOptionLabels(),
            'operations' => $operations,
            'historyFilters' => [
                'search' => $historySearch,
                'status' => $historyStatus?->value ?? '',
            ],
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
