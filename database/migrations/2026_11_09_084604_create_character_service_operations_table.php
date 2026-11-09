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
        Schema::create('character_service_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10)->index();
            $table->string('service_type', 32);
            $table->string('character_name', 10);
            $table->string('requested_name', 10)->nullable();
            $table->unsignedTinyInteger('target_class_code')->nullable();
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('price_credits');
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->noActionOnDelete();
            $table->foreignId('refund_wallet_transaction_id')->nullable()->constrained('wallet_transactions')->noActionOnDelete();
            $table->json('settings_snapshot');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_service_operations');
    }
};
