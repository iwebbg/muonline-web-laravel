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
        Schema::create('online_time_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10);
            $table->unsignedBigInteger('credits_claimed');
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('online_time_accounts')->cascadeOnDelete();
            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_time_claims');
    }
};
