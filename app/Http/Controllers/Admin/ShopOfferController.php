<?php

namespace App\Http\Controllers\Admin;

use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemGrantRules97d99i;
use App\Http\AccountPanelConfiguration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShopOfferRequest;
use App\Http\Requests\Admin\UpdateShopOfferRequest;
use App\Models\ShopOffer;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Manages catalog-backed Items Shop and Mystery Box offers.
 */
final class ShopOfferController extends Controller
{
    /**
     * Displays searchable, filterable shop offers with pagination.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $type = ShopOfferType::tryFrom($request->string('type')->toString());
        $query = ShopOffer::query()->withCount('items')->latest('id');

        if ($search !== '') {
            $query->where(function ($offerQuery) use ($search): void {
                $offerQuery
                    ->whereLike('title_en', '%'.$search.'%')
                    ->orWhereLike('title_bg', '%'.$search.'%');
            });
        }

        if ($type instanceof ShopOfferType) {
            $query->where('type', $type);
        }

        return view('admin.shop-offers.index', [
            'offers' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'selectedType' => $type,
            'types' => ShopOfferType::cases(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(
        ItemCatalog97d99i $catalog,
        ItemGrantRules97d99i $rules,
        AccountPanelConfiguration $accountPanelConfiguration,
    ): View {
        return view('admin.shop-offers.form', $this->formData(
            null,
            $catalog,
            $rules,
            $accountPanelConfiguration,
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreShopOfferRequest $request): RedirectResponse
    {
        $offer = DB::transaction(function () use ($request): ShopOffer {
            $offer = ShopOffer::query()->create([
                ...$request->offerData(),
                'public_id' => (string) Str::uuid(),
            ]);
            $offer->items()->createMany($request->itemData());

            return $offer;
        });

        return redirect()
            ->route('admin.shop-offers.edit', $offer)
            ->with('status', __('ui.admin.shop_offer_created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(
        ShopOffer $shopOffer,
        ItemCatalog97d99i $catalog,
        ItemGrantRules97d99i $rules,
        AccountPanelConfiguration $accountPanelConfiguration,
    ): View {
        $shopOffer->load('items');

        return view('admin.shop-offers.form', $this->formData(
            $shopOffer,
            $catalog,
            $rules,
            $accountPanelConfiguration,
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateShopOfferRequest $request,
        ShopOffer $shopOffer,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $shopOffer): void {
            $shopOffer->update($request->offerData());
            $shopOffer->items()->delete();
            $shopOffer->items()->createMany($request->itemData());
        });

        return back()->with('status', __('ui.admin.shop_offer_updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShopOffer $shopOffer): RedirectResponse
    {
        $shopOffer->update(['status' => ShopOfferStatus::Archived]);

        return redirect()
            ->route('admin.shop-offers.index')
            ->with('status', __('ui.admin.shop_offer_archived'));
    }

    /**
     * Prepares trusted item definitions and finite offer choices for the editor.
     *
     * @return array<string, mixed>
     */
    private function formData(
        ?ShopOffer $offer,
        ItemCatalog97d99i $catalog,
        ItemGrantRules97d99i $rules,
        AccountPanelConfiguration $accountPanelConfiguration,
    ): array {
        $definitions = array_map(static fn ($definition): array => [
            'code' => $definition->code(),
            'name' => $definition->name,
            'image' => $definition->image,
            'excellentType' => $definition->excellentType,
            'optionType' => $definition->optionType,
        ], $catalog->allDefinitions());

        return [
            'offer' => $offer,
            'definitionsByCode' => array_column($definitions, null, 'code'),
            'types' => ShopOfferType::cases(),
            'classScopes' => app(GameVersionProfile::class)->shopClassScopes(),
            'statuses' => ShopOfferStatus::cases(),
            'itemLevels' => $rules->itemLevels(),
            'optionLevels' => $rules->optionLevels(),
            'itemOptionLabels' => $accountPanelConfiguration->itemOptionLabels(),
        ];
    }
}
