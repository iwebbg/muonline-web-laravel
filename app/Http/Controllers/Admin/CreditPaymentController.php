<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCreditPaymentsRequest;
use App\Models\CreditPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/** Displays searchable and filterable Credits purchase history. */
final class CreditPaymentController extends Controller
{
    /**
     * Returns a bounded page of provider purchases for economy administrators.
     */
    public function __invoke(FilterCreditPaymentsRequest $request): View
    {
        $search = $request->search();
        $status = $request->status();
        $provider = $request->provider();
        $dateFrom = $request->dateFrom();
        $dateTo = $request->dateTo();
        $paymentsQuery = CreditPayment::query();

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $paymentsQuery->where(function (Builder $query) use ($searchPattern): void {
                $query
                    ->whereLike('account_id', $searchPattern)
                    ->orWhereLike('public_id', $searchPattern)
                    ->orWhereLike('provider_order_id', $searchPattern)
                    ->orWhereLike('provider_transaction_id', $searchPattern);
            });
        }

        if ($status !== null) {
            $paymentsQuery->where('status', $status);
        }

        if ($provider !== null) {
            $paymentsQuery->where('provider', $provider);
        }

        if ($dateFrom !== null) {
            $paymentsQuery->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $paymentsQuery->where('created_at', '<=', $dateTo);
        }

        $payments = $paymentsQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.credit-payments', [
            'payments' => $payments,
            'paymentFilters' => [
                'search' => $search,
                'status' => $status?->value ?? '',
                'provider' => $provider?->value ?? '',
                'dateFrom' => $request->string('date_from')->toString(),
                'dateTo' => $request->string('date_to')->toString(),
            ],
        ]);
    }
}
