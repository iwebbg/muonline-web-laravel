<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** Verifies the permission-scoped Credits purchase history and its filters. */
final class AdminCreditPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Confirms package management and purchase history are separate protected tools. */
    public function test_purchase_history_is_separate_and_requires_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get(route('admin.credit-packages.index'))
            ->assertOk()
            ->assertSee('Credits packages')
            ->assertDontSee('Account ID or payment reference…');
        $this->actingAs($economyManager, 'admin')
            ->get(route('admin.credit-payments.index'))
            ->assertOk()
            ->assertSee('Credits purchase history')
            ->assertSee('Account ID or payment reference…')
            ->assertSee('Date from')
            ->assertSee('Date to');
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.credit-payments.index'))
            ->assertForbidden();
    }

    /** Confirms search, provider, state and inclusive dates constrain the same query. */
    public function test_purchase_history_combines_search_provider_status_and_date_filters(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $package = CreditPackage::factory()->create();
        $matchingSnapshot = [
            'title_en' => '<script>alert("payment")</script>',
            'title_bg' => 'Сигурен пакет',
            'credits' => 500,
            'price_minor' => 500,
            'currency' => 'EUR',
        ];
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'target01',
            'status' => CreditPaymentStatus::Paid,
            'provider' => PaymentProvider::Stripe,
            'package_snapshot' => $matchingSnapshot,
            'created_at' => '2026-11-01 00:00:00',
        ]);
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'target02',
            'status' => CreditPaymentStatus::Paid,
            'provider' => PaymentProvider::Stripe,
            'package_snapshot' => $matchingSnapshot,
            'created_at' => '2026-11-02 23:59:59',
        ]);
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'target03',
            'status' => CreditPaymentStatus::Failed,
            'provider' => PaymentProvider::Stripe,
            'created_at' => '2026-11-02 12:00:00',
        ]);
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'target04',
            'status' => CreditPaymentStatus::Paid,
            'provider' => PaymentProvider::Paypal,
            'created_at' => '2026-11-02 12:00:00',
        ]);
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'target05',
            'status' => CreditPaymentStatus::Paid,
            'provider' => PaymentProvider::Stripe,
            'created_at' => '2026-10-31 23:59:59',
        ]);
        CreditPayment::factory()->for($package, 'package')->create([
            'account_id' => 'other001',
            'status' => CreditPaymentStatus::Paid,
            'provider' => PaymentProvider::Stripe,
            'created_at' => '2026-11-02 12:00:00',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.credit-payments.index', [
                'search' => 'target',
                'status' => CreditPaymentStatus::Paid->value,
                'provider' => PaymentProvider::Stripe->value,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-02',
            ]));

        $response
            ->assertOk()
            ->assertSee('target01')
            ->assertSee('target02')
            ->assertDontSee('target03')
            ->assertDontSee('target04')
            ->assertDontSee('target05')
            ->assertDontSee('other001')
            ->assertDontSee('<script>alert("payment")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertViewHas('payments', function (mixed $payments): bool {
                return $payments instanceof LengthAwarePaginator
                    && $payments->total() === 2;
            });
    }

    /** Confirms purchase history remains bounded and retains filters in page links. */
    public function test_purchase_history_is_paginated_with_filters(): void
    {
        $this->travelTo('2026-11-02 12:00:00');
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $package = CreditPackage::factory()->create();
        CreditPayment::factory()
            ->count(21)
            ->for($package, 'package')
            ->create([
                'status' => CreditPaymentStatus::Pending,
                'provider' => PaymentProvider::Stripe,
            ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.credit-payments.index', [
                'status' => CreditPaymentStatus::Pending->value,
                'provider' => PaymentProvider::Stripe->value,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-03',
            ]));

        $response
            ->assertOk()
            ->assertViewHas('payments', function (mixed $payments): bool {
                if (! $payments instanceof LengthAwarePaginator) {
                    return false;
                }

                $secondPageUrl = $payments->url(2);

                return $payments->count() === 20
                    && $payments->total() === 21
                    && $payments->lastPage() === 2
                    && str_contains($secondPageUrl, 'status=pending')
                    && str_contains($secondPageUrl, 'provider=stripe')
                    && str_contains($secondPageUrl, 'date_from=2026-11-01')
                    && str_contains($secondPageUrl, 'date_to=2026-11-03');
            });
    }

    /** Confirms an inverted purchase period is rejected before history is queried. */
    public function test_purchase_history_rejects_inverted_date_range(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.credit-payments.index', [
                'date_from' => '2026-11-03',
                'date_to' => '2026-11-01',
            ]));

        $response->assertSessionHasErrors('date_to');
    }
}
