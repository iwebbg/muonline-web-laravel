<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVipPlanRequest;
use App\Http\Requests\Admin\UpdateVipPlanRequest;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Manages bilingual character VIP plans in the application database.
 */
final class VipPlanController extends Controller
{
    /**
     * Displays plans and the newest synchronization state for economy managers.
     */
    public function index(): View
    {
        $plans = VipPlan::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $recentEntitlements = VipEntitlement::query()
            ->latest('updated_at')
            ->limit(25)
            ->get();

        return view('admin.vip-plans', [
            'plans' => $plans,
            'recentEntitlements' => $recentEntitlements,
        ]);
    }

    /**
     * Creates one player-visible character VIP offer.
     */
    public function store(StoreVipPlanRequest $request): RedirectResponse
    {
        VipPlan::query()->create($request->planData());

        return back()->with('status', __('ui.admin.vip_plan_created'));
    }

    /**
     * Updates one plan without changing immutable historical purchase snapshots.
     */
    public function update(
        UpdateVipPlanRequest $request,
        VipPlan $vipPlan,
    ): RedirectResponse {
        $vipPlan->update($request->planData());

        return back()->with('status', __('ui.admin.vip_plan_updated'));
    }

    /**
     * Soft deletes one plan while preserving purchases and current entitlements.
     */
    public function destroy(VipPlan $vipPlan): RedirectResponse
    {
        $vipPlan->delete();

        return back()->with('status', __('ui.admin.vip_plan_deleted'));
    }
}
