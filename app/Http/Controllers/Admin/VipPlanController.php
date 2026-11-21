<?php

namespace App\Http\Controllers\Admin;

use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVipPlanRequest;
use App\Http\Requests\Admin\UpdateVipPlanRequest;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use Illuminate\Database\Eloquent\Builder;
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
    public function index(GameVersionProfile $profile): View
    {
        $plans = VipPlan::query()
            ->when(
                $profile->capabilities()->accountVip,
                fn (Builder $query): Builder => $query->whereNotNull('native_account_level'),
                fn (Builder $query): Builder => $query->whereNull('native_account_level'),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $recentEntitlements = VipEntitlement::query()
            ->latest('updated_at')
            ->limit(25)
            ->get();

        return view('admin.vip-plans', [
            'plans' => $plans,
            'nativeAccountVip' => $profile->capabilities()->accountVip,
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
        GameVersionProfile $profile,
    ): RedirectResponse {
        $this->assertPlanScope($vipPlan, $profile);
        $vipPlan->update($request->planData());

        return back()->with('status', __('ui.admin.vip_plan_updated'));
    }

    /**
     * Soft deletes one plan while preserving purchases and current entitlements.
     */
    public function destroy(VipPlan $vipPlan, GameVersionProfile $profile): RedirectResponse
    {
        $this->assertPlanScope($vipPlan, $profile);
        $vipPlan->delete();

        return back()->with('status', __('ui.admin.vip_plan_deleted'));
    }

    /**
     * Prevents native account plans and legacy character plans from editing or replacing each other.
     */
    private function assertPlanScope(VipPlan $plan, GameVersionProfile $profile): void
    {
        abort_unless(($plan->native_account_level !== null) === $profile->capabilities()->accountVip, 404);
    }
}
