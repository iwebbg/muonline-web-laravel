<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreZenBankOperationRequest;
use App\Models\AccountZenBalance;
use App\Models\ZenBankOperation;
use App\ZenBank\Actions\CreateZenBankOperation;
use App\ZenBank\Exceptions\ZenBankRejected;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays and mutates the recoverable player Zen Bank.
 */
final class ZenBankController extends Controller
{
    /**
     * Creates the controller with account and warehouse read boundaries.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ZenBankRepository $zenBankRepository,
    ) {}

    /**
     * Displays bank balance, warehouse balance and recent transfer history.
     */
    public function index(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characters = $this->accountReadRepository->charactersFor($account->username);
        $balance = AccountZenBalance::query()
            ->where('account_id', $account->username)
            ->value('balance');
        $operations = ZenBankOperation::query()
            ->where('account_id', $account->username)
            ->latest('id')
            ->limit(50)
            ->get();

        return view('account.zen-bank', [
            'characters' => $characters,
            'bankBalance' => (int) ($balance ?? 0),
            'warehouseBalance' => $this->zenBankRepository->currentBalance($account->username),
            'operations' => $operations,
            'depositIdempotencyKey' => (string) Str::uuid(),
            'withdrawalIdempotencyKey' => (string) Str::uuid(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Creates and attempts one deposit or withdrawal operation.
     */
    public function store(
        StoreZenBankOperationRequest $request,
        CreateZenBankOperation $createZenBankOperation,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $operation = $createZenBankOperation->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                type: ZenBankOperationType::from($request->string('type')->toString()),
                amount: $request->integer('amount'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (ZenBankRejected $exception) {
            return back()->withErrors([
                'zenBank' => __('ui.zen_bank.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $operation->status === ZenBankOperationStatus::Completed
            ? 'ui.zen_bank.completed'
            : 'ui.zen_bank.pending';

        return back()->with('zenBankStatus', __($messageKey));
    }
}
