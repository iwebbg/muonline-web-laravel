<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCreditPackageRequest;
use App\Http\Requests\Admin\UpdateCreditPackageRequest;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Payments\CreditPackageStatus;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Manages Credits packages and exposes provider payment history. */
final class CreditPackageController extends Controller
{
    public function index(Request $request, PaymentGatewayManager $gateways): View
    {
        $selectedStatus = $request->string('status')->toString();
        $payments = CreditPayment::query()->latest('id');

        if ($selectedStatus !== '') {
            $payments->where('status', $selectedStatus);
        }

        return view('admin.credit-packages', [
            'packages' => CreditPackage::query()->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => $payments->paginate(20)->withQueryString(),
            'statuses' => CreditPackageStatus::cases(),
            'enabledProviders' => $gateways->enabledProviders(),
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function store(StoreCreditPackageRequest $request): RedirectResponse
    {
        CreditPackage::query()->create([...$request->packageData(), 'public_id' => (string) Str::uuid()]);

        return back()->with('status', __('ui.admin.credit_package_created'));
    }

    public function update(UpdateCreditPackageRequest $request, CreditPackage $creditPackage): RedirectResponse
    {
        $creditPackage->update($request->packageData());

        return back()->with('status', __('ui.admin.credit_package_updated'));
    }

    public function destroy(CreditPackage $creditPackage): RedirectResponse
    {
        $creditPackage->update(['status' => CreditPackageStatus::Archived]);

        return back()->with('status', __('ui.admin.credit_package_archived'));
    }
}
