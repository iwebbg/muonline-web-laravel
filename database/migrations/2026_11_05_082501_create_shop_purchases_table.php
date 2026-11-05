<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10)->index();
            $table->foreignId('shop_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->json('offer_snapshot');
            $table->unsignedBigInteger('price_credits');
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_purchases');
    }
};
