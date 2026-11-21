<?php

namespace App\Http\Controllers\Admin;

use App\Game\Repositories\SqlServerMuDevsSeason21WarehouseInspectionRepository;
use App\Game\Versions\MuDevsSeason21\WarehousePreviewMuDevsSeason21;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InspectMuDevsSeason21WarehouseRequest;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Shows account-scoped S21 item geometry and verified fields during adapter commissioning.
 */
final class MuDevsSeason21WarehouseController extends Controller
{
    /**
     * Renders saved native records without activating the full profile or exposing write actions.
     */
    public function __invoke(
        InspectMuDevsSeason21WarehouseRequest $request,
        SqlServerMuDevsSeason21WarehouseInspectionRepository $inspector,
        WarehousePreviewMuDevsSeason21 $presenter,
    ): View {
        abort_unless(config('game.s21_warehouse_preview_enabled'), 404);

        $validated = $request->validated();
        $accountId = $validated['account'] ?? '';
        $vaultNumber = (int) ($validated['vault'] ?? 0);
        $items = [];
        $storageError = null;
        $records = null;

        if ($accountId !== '') {
            try {
                $records = $inspector->forAccount($accountId, $vaultNumber);
            } catch (InvalidArgumentException|UnexpectedValueException $exception) {
                $storageError = __('ui.s21_warehouse.invalid_storage');
            }

            if ($records === null && $storageError === null) {
                $storageError = __('ui.s21_warehouse.missing_storage');
            }

            if ($records !== null) {
                $items = $presenter->items($records);
            }
        }

        return view('admin.s21-warehouse', [
            'accountId' => $accountId,
            'vaultNumber' => $vaultNumber,
            'items' => $items,
            'hasStorage' => $records !== null,
            'storageError' => $storageError,
        ]);
    }
}
