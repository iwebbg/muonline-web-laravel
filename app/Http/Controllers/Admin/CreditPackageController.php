<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCreditPackagesRequest;
use App\Http\Requests\Admin\StoreCreditPackageRequest;
use App\Http\Requests\Admin\UpdateCreditPackageRequest;
use App\Models\CreditPackage;
use App\Payments\CreditPackageStatus;
use App\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Manages fixed-value Credits packages. */
final class CreditPackageController extends Controller
{
    /** Displays searchable package controls with bounded pagination. */
    public function index(
        FilterCreditPackagesRequest $request,
        PaymentGatewayManager $gateways,
    ): View {
        $search = $request->search();
        $status = $request->status();
        $packagesQuery = CreditPackage::query();

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $packagesQuery->where(function (Builder $query) use ($searchPattern): void {
                $query
                    ->whereLike('title_en', $searchPattern)
                    ->orWhereLike('title_bg', $searchPattern);
            });
        }

        if ($status !== null) {
            $packagesQuery->where('status', $status);
        }

        $packages = $packagesQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.credit-packages', [
            'packages' => $packages,
            'statuses' => CreditPackageStatus::cases(),
            'enabledProviders' => $gateways->enabledProviders(),
            'packageFilters' => [
                'search' => $search,
                'status' => $status?->value ?? '',
            ],
        ]);
    }

    /** Creates one package with an immutable public identifier. */
    public function store(StoreCreditPackageRequest $request): RedirectResponse
    {
        CreditPackage::query()->create([...$request->packageData(), 'public_id' => (string) Str::uuid()]);

        return back()->with('status', __('ui.admin.credit_package_created'));
    }

    /** Updates the package while existing purchases retain their snapshots. */
    public function update(UpdateCreditPackageRequest $request, CreditPackage $creditPackage): RedirectResponse
    {
        $creditPackage->update($request->packageData());

        return back()->with('status', __('ui.admin.credit_package_updated'));
    }

    /** Archives a package without removing historical purchase references. */
    public function destroy(CreditPackage $creditPackage): RedirectResponse
    {
        $creditPackage->update(['status' => CreditPackageStatus::Archived]);

        return back()->with('status', __('ui.admin.credit_package_archived'));
    }
}
