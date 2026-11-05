<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\CreditPackage;
use App\Payments\CreditPackageStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** Verifies package management permissions, validation and persistence. */
final class AdminCreditPackageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_economy_manager_creates_a_fixed_credits_package(): void
    {
        $administrator = Administrator::factory()->gameMaster([AdministratorPermission::ManageEconomy])->create();
        $csrfToken = 'credit-package-create-token';

        $this->actingAs($administrator, 'admin')->withSession(['_token' => $csrfToken])->post(route('admin.credit-packages.store'), [
            '_token' => $csrfToken,
            'title_en' => 'Warrior Pack', 'title_bg' => 'Воински пакет', 'credits' => 1200,
            'price' => '9.99', 'currency' => 'EUR', 'status' => CreditPackageStatus::Published->value, 'sort_order' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $package = CreditPackage::query()->sole();
        $this->assertSame(999, $package->price_minor);
        $this->assertSame(1200, $package->credits);
        $this->assertSame(CreditPackageStatus::Published, $package->status);
    }

    public function test_administrator_without_economy_permission_is_forbidden(): void
    {
        $administrator = Administrator::factory()->gameMaster([AdministratorPermission::ModerateAccounts])->create();

        $this->actingAs($administrator, 'admin')->get(route('admin.credit-packages.index'))->assertForbidden();
    }

    /** Confirms package search, status filtering and pagination share the compact catalog screen. */
    public function test_package_catalog_is_filterable_paginated_and_uses_the_shared_editor(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        CreditPackage::factory()->count(21)->create([
            'title_en' => 'Featured Credits',
            'status' => CreditPackageStatus::Published,
        ]);
        CreditPackage::factory()->create([
            'title_en' => 'Hidden Credits',
            'status' => CreditPackageStatus::Draft,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.credit-packages.index', [
                'search' => 'Featured',
                'status' => CreditPackageStatus::Published->value,
            ]));

        $response
            ->assertOk()
            ->assertSee('Featured Credits')
            ->assertDontSee('Hidden Credits')
            ->assertSee('Create package')
            ->assertSee('Edit package')
            ->assertSee('x-teleport="body"', false)
            ->assertViewHas('packages', function (mixed $packages): bool {
                if (! $packages instanceof LengthAwarePaginator) {
                    return false;
                }

                $secondPageUrl = $packages->url(2);

                return $packages->count() === 20
                    && $packages->total() === 21
                    && $packages->lastPage() === 2
                    && str_contains($secondPageUrl, 'search=Featured')
                    && str_contains($secondPageUrl, 'status=published');
            });
    }
}
