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
        Schema::create('zen_bank_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10)->index();
            $table->string('character_name', 10);
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('before_game_zen');
            $table->unsignedBigInteger('after_game_zen');
            $table->unsignedBigInteger('before_bank_balance');
            $table->unsignedBigInteger('after_bank_balance');
            $table->string('status', 24)->index();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->dateTime('last_sync_attempt_at')->nullable();
            $table->string('last_sync_error', 80)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->foreignId('account_zen_balance_id')
                ->constrained('account_zen_balances')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zen_bank_operations');
    }
};
