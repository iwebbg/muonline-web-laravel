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
        Schema::create('credit_item_exchanges', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10)->index();
            $table->string('character_name', 10);
            $table->foreignId('credit_exchange_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('credits_awarded');
            $table->json('offer_snapshot');
            $table->json('items_snapshot');
            $table->string('status', 30);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_item_exchanges');
    }
};
