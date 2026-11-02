<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrator;
use App\Models\EconomyRecoveryAction;
use App\Models\WebStorageItem;
use App\Models\ZenBankOperation;
use App\WebStorage\Actions\SynchronizeWebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use App\ZenBank\Actions\SynchronizeZenBankOperation;
use App\ZenBank\ZenBankOperationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provides permission-scoped recovery for item custody and Zen transfers.
 */
final class EconomyRecoveryController extends Controller
{
    /**
     * Displays pending and conflicted economy operations with recent admin actions.
     */
    public function index(): View
    {
        $storageItems = WebStorageItem::query()
            ->whereIn('status', [
                WebStorageItemStatus::Storing->value,
                WebStorageItemStatus::WithdrawalPending->value,
                WebStorageItemStatus::Failed->value,
            ])
            ->latest('id')
            ->limit(100)
            ->get();
        $zenOperations = ZenBankOperation::query()
            ->whereIn('status', [
                ZenBankOperationStatus::Pending->value,
                ZenBankOperationStatus::Conflict->value,
            ])
            ->latest('id')
            ->limit(100)
            ->get();
        $recentActions = EconomyRecoveryAction::query()
            ->with('administrator:id,name')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('admin.economy-recovery', [
            'storageItems' => $storageItems,
            'zenOperations' => $zenOperations,
            'recentActions' => $recentActions,
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Retries one pending exact-item extraction or warehouse return.
     */
    public function retryStorage(
        Request $request,
        WebStorageItem $webStorageItem,
        SynchronizeWebStorageItem $synchronizeWebStorageItem,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        if (! in_array(
            $webStorageItem->status,
            [WebStorageItemStatus::Storing, WebStorageItemStatus::WithdrawalPending],
            true,
        )) {
            return back()->withErrors([
                'recovery' => __('ui.admin.recovery_retry_unavailable'),
            ]);
        }

        $beforeStatus = $webStorageItem->status->value;
        $synchronized = $synchronizeWebStorageItem->execute($webStorageItem);
        $webStorageItem->refresh();
        $this->recordAction(
            administrator: $administrator,
            subjectType: 'item_custody',
            subjectPublicId: $webStorageItem->public_id,
            accountId: $webStorageItem->account_id,
            beforeStatus: $beforeStatus,
            afterStatus: $webStorageItem->status->value,
            outcome: $synchronized
                ? 'completed'
                : ($webStorageItem->status === WebStorageItemStatus::Failed ? 'failed' : 'pending'),
            failureCode: $webStorageItem->last_sync_error,
            syncAttempts: $webStorageItem->sync_attempts,
        );

        return back()->with(
            'status',
            $synchronized
                ? __('ui.admin.recovery_retry_completed')
                : __('ui.admin.recovery_retry_pending'),
        );
    }

    /**
     * Retries one pending compare-and-set Zen transfer.
     */
    public function retryZen(
        Request $request,
        ZenBankOperation $zenBankOperation,
        SynchronizeZenBankOperation $synchronizeZenBankOperation,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        if ($zenBankOperation->status !== ZenBankOperationStatus::Pending) {
            return back()->withErrors([
                'recovery' => __('ui.admin.recovery_retry_unavailable'),
            ]);
        }

        $beforeStatus = $zenBankOperation->status->value;
        $synchronized = $synchronizeZenBankOperation->execute($zenBankOperation);
        $zenBankOperation->refresh();
        $this->recordAction(
            administrator: $administrator,
            subjectType: 'zen_bank',
            subjectPublicId: $zenBankOperation->public_id,
            accountId: $zenBankOperation->account_id,
            beforeStatus: $beforeStatus,
            afterStatus: $zenBankOperation->status->value,
            outcome: $synchronized
                ? 'completed'
                : ($zenBankOperation->status === ZenBankOperationStatus::Conflict ? 'failed' : 'pending'),
            failureCode: $zenBankOperation->last_sync_error,
            syncAttempts: $zenBankOperation->sync_attempts,
        );

        return back()->with(
            'status',
            $synchronized
                ? __('ui.admin.recovery_retry_completed')
                : __('ui.admin.recovery_retry_pending'),
        );
    }

    /**
     * Persists one immutable administrator recovery attempt.
     */
    private function recordAction(
        Administrator $administrator,
        string $subjectType,
        string $subjectPublicId,
        string $accountId,
        string $beforeStatus,
        string $afterStatus,
        string $outcome,
        ?string $failureCode,
        int $syncAttempts,
    ): void {
        EconomyRecoveryAction::query()->create([
            'administrator_id' => $administrator->getKey(),
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'account_id' => $accountId,
            'action' => 'retry_sync',
            'before_status' => $beforeStatus,
            'after_status' => $afterStatus,
            'outcome' => $outcome,
            'failure_code' => $failureCode,
            'context_snapshot' => ['sync_attempts' => $syncAttempts],
        ]);
    }
}
