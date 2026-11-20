<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds scoped exchange rates, immutable purchase audits and optional native VIP tier metadata.
     */
    public function up(): void
    {
        Schema::create('game_currency_exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('game_profile_key', 64);
            $table->string('currency', 32);
            $table->unsignedInteger('credit_units')->default(1);
            $table->unsignedInteger('coin_units')->default(1);
            $table->unsignedInteger('minimum_units')->default(1);
            $table->unsignedInteger('maximum_units')->default(1000000);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['game_profile_key', 'currency'], 'game_currency_rate_profile_currency_unique');
        });
        Schema::create('game_currency_exchange_rate_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_currency_exchange_rate_id')->constrained();
            $table->foreignId('administrator_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->json('before_snapshot');
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
        Schema::create('native_account_purchases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('game_profile_key', 64);
            $table->string('account_id', 10);
            $table->string('kind', 32);
            $table->unsignedBigInteger('product_id');
            $table->string('character_name', 10)->nullable();
            $table->unsignedInteger('units');
            $table->unsignedBigInteger('credit_cost');
            $table->string('status', 16);
            $table->json('mutation_snapshot');
            $table->json('settings_snapshot');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions');
            $table->foreignId('refund_wallet_transaction_id')->nullable()->constrained('wallet_transactions');
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });
        Schema::table('vip_plans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('native_account_level')->nullable();
        });
        foreach (['wcoin_c', 'wcoin_p', 'goblin_points', 'lucky_coins', 'ruud'] as $currency) {
            DB::table('game_currency_exchange_rates')->insert([
                'game_profile_key' => 'mudevs_season_21', 'currency' => $currency,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Refuses to remove financial history, operator revisions or populated native VIP metadata.
     */
    public function down(): void
    {
        if (DB::table('native_account_purchases')->exists()
            || DB::table('game_currency_exchange_rate_revisions')->exists()
            || DB::table('vip_plans')->whereNotNull('native_account_level')->exists()) {
            throw new RuntimeException('Native purchases or configured VIP tiers require a forward migration.');
        }
        Schema::table('vip_plans', function (Blueprint $table): void {
            $table->dropColumn('native_account_level');
        });
        Schema::dropIfExists('native_account_purchases');
        Schema::dropIfExists('game_currency_exchange_rate_revisions');
        Schema::dropIfExists('game_currency_exchange_rates');
    }
};
