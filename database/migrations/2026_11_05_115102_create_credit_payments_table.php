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
        Schema::create('credit_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('account_id', 10)->index();
            $table->foreignId('credit_package_id')->nullable()->constrained()->nullOnDelete();
            $table->json('package_snapshot');
            $table->string('provider', 20)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('credits');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('provider_order_id', 191)->nullable();
            $table->string('provider_transaction_id', 191)->nullable();
            $table->json('provider_payload')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_order_id']);
            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
    }
};
