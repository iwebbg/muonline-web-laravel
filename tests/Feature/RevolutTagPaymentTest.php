<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Auth\GameAccount;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use App\Models\Administrator;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Models\ManualPaymentReceipt;
use App\Models\WalletTransaction;
use App\Payments\CreditPaymentStatus;
use App\Payments\Gateways\RevolutTagGateway;
use App\Payments\PaymentProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Verifies manual transfer instructions, review authorization and exactly-once Credits. */
final class RevolutTagPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Keeps the manual provider hidden unless both configuration values are valid. */
    public function test_requires_explicit_enablement_and_a_valid_tag(): void
    {
        $gateway = new RevolutTagGateway;
        config()->set('services.payments.revolut_tag', ['enabled' => false, 'tag' => '@server']);
        self::assertFalse($gateway->isEnabled());
        config()->set('services.payments.revolut_tag', ['enabled' => true, 'tag' => '']);
        self::assertFalse($gateway->isEnabled());
        config()->set('services.payments.revolut_tag', ['enabled' => true, 'tag' => '@server']);
        self::assertTrue($gateway->isEnabled());
    }

    /** Creates pending instructions with immutable recipient, amount and account terms. */
    #[TestWith([Version97d99iProfile::class])]
    #[TestWith([Version1_0MProfile::class])]
    #[TestWith([MuEmuSeason6Profile::class])]
    #[TestWith([MuDevsSeason21Profile::class])]
    public function test_purchase_snapshots_transfer_instructions_and_never_credits_on_browser_return(string $profileClass): void
    {
        $this->app->instance(GameVersionProfile::class, new $profileClass);
        config()->set('services.payments.revolut_tag', ['enabled' => true, 'tag' => '@server']);
        Http::preventStrayRequests();
        $package = CreditPackage::factory()->create(['credits' => 1200, 'price_minor' => 999, 'currency' => 'EUR']);
        $this->actingAs($this->account())->withSession(['_token' => 'payment-token']);
        $response = $this->post(route('account.credit-packages.purchase', $package), [
            '_token' => 'payment-token', 'provider' => 'revolut_tag',
        ]);
        $payment = CreditPayment::query()->sole();
        $response->assertRedirect(route('account.revolut-tag.show', $payment));
        $package->update(['price_minor' => 2000, 'credits' => 1]);
        config()->set('services.payments.revolut_tag.tag', '@changed');

        $this->get(route('account.revolut-tag.show', $payment))->assertOk()
            ->assertSee('@server')->assertSee('9.99 EUR')->assertSee('buyer01')->assertSee('1,200')
            ->assertSee('https://revolut.me/server?currency=EUR&amount=999&note=buyer01')
            ->assertSee('Open Revolut to pay');
        $this->get(route('account.credit-payments.return', ['creditPayment' => $payment, 'provider' => 'revolut_tag', 'paid' => 1]))
            ->assertRedirect(route('account.credits.index'));
        self::assertSame(CreditPaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNothingSent();
    }

    /** Keeps malformed saved recipients out of the external payment destination. */
    public function test_invalid_saved_recipient_does_not_produce_a_payment_link(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['provider_payload' => ['recipient_tag' => '@server/other?note=wrong']]);

        $this->actingAs($this->account())
            ->get(route('account.revolut-tag.show', $payment))
            ->assertOk()
            ->assertDontSee('Open Revolut to pay')
            ->assertDontSee('href="https://revolut.me/', false);
    }

    /** Hides another account's instructions and refuses forged manual webhooks. */
    public function test_transfer_instructions_are_private_and_have_no_public_confirmation_endpoint(): void
    {
        $payment = CreditPayment::factory()->create(['account_id' => 'buyer01', 'provider' => PaymentProvider::RevolutTag]);
        $this->actingAs($this->account('other01'))->get(route('account.revolut-tag.show', $payment))->assertNotFound();
        $this->postJson('/payments/webhooks/revolut_tag', ['paid' => true])->assertNotFound();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /** Credits the saved package once and attributes the receipt and ledger to its reviewer. */
    public function test_administrator_confirmation_is_audited_and_idempotent(): void
    {
        $this->mock(GameAccountLookupRepository::class)->shouldReceive('exists')->once()->with('buyer01')->andReturnTrue();
        $payment = $this->pendingPayment();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin')->withSession(['_token' => 'payment-token']);
        $url = route('admin.credit-payments.confirm-revolut-tag', $payment);
        $data = ['_token' => 'payment-token', 'reference' => 'TRANSFER-123', 'received' => 1];

        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();

        self::assertSame(CreditPaymentStatus::Paid, $payment->refresh()->status);
        $this->assertDatabaseHas('account_wallets', ['account_id' => 'buyer01', 'balance' => 500]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('manual_payment_receipts', 1);
        self::assertSame($administrator->getKey(), WalletTransaction::query()->sole()->performed_by_administrator_id);
        self::assertSame('transfer-123', ManualPaymentReceipt::query()->sole()->reference);
        $this->get(route('admin.credit-payments.index'))->assertOk()->assertSee('Revolut Tag');
    }

    /** Stops one received transfer from crediting two independently created purchases. */
    public function test_transfer_reference_cannot_be_reused_for_another_payment(): void
    {
        $this->mock(GameAccountLookupRepository::class)->shouldReceive('exists')->once()->andReturnTrue();
        $first = $this->pendingPayment();
        $second = $this->pendingPayment();
        $this->actingAs(Administrator::factory()->create(), 'admin')->withSession(['_token' => 'payment-token']);
        $data = ['_token' => 'payment-token', 'reference' => 'same-transfer', 'received' => 1];
        $this->post(route('admin.credit-payments.confirm-revolut-tag', $first), $data)->assertRedirect();
        $this->post(route('admin.credit-payments.confirm-revolut-tag', $second), $data)
            ->assertSessionHasErrors('reference');
        self::assertSame(CreditPaymentStatus::Pending, $second->refresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('manual_payment_receipts', 1);
    }

    /** Refuses missing review, unauthorized reviewers and non-manual purchases. */
    public function test_confirmation_requires_economy_permission_and_verified_receipt(): void
    {
        $payment = $this->pendingPayment();
        $this->actingAs(Administrator::factory()->gameMaster([AdministratorPermission::ManageContent])->create(), 'admin')
            ->withSession(['_token' => 'payment-token']);
        $url = route('admin.credit-payments.confirm-revolut-tag', $payment);
        $this->post($url, ['_token' => 'payment-token'])->assertForbidden();
        $this->actingAs(Administrator::factory()->create(), 'admin');
        $this->post($url, ['_token' => 'payment-token'])->assertSessionHasErrors(['reference', 'received']);
        $payment->update(['provider' => PaymentProvider::Stripe]);
        $this->post($url, ['_token' => 'payment-token', 'reference' => 'transfer', 'received' => 1])->assertNotFound();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /** Rolls back the receipt when the account cannot receive Credits. */
    public function test_failed_wallet_credit_rolls_back_the_manual_receipt(): void
    {
        $this->mock(GameAccountLookupRepository::class)->shouldReceive('exists')->once()->andReturnFalse();
        $payment = $this->pendingPayment();
        $this->actingAs(Administrator::factory()->create(), 'admin')->withSession(['_token' => 'payment-token'])
            ->post(route('admin.credit-payments.confirm-revolut-tag', $payment), [
                '_token' => 'payment-token', 'reference' => 'not-used', 'received' => 1,
            ])->assertSessionHasErrors('payment');
        self::assertSame(CreditPaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('manual_payment_receipts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /** Creates a purchase with the immutable recipient shown to its owner. */
    private function pendingPayment(): CreditPayment
    {
        return CreditPayment::factory()->create([
            'account_id' => 'buyer01',
            'provider' => PaymentProvider::RevolutTag,
            'provider_payload' => ['recipient_tag' => '@server', 'reference' => 'buyer01'],
        ]);
    }

    /** Creates the same isolated game identity used by existing payment tests. */
    private function account(string $username = 'buyer01'): GameAccount
    {
        return new GameAccount(1, $username, 'Buyer', 'buyer@example.com', false, false, 'secret1');
    }
}
