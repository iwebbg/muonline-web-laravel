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
        foreach (['character_reset_operations', 'character_grand_reset_operations'] as $operationTable) {
            Schema::table($operationTable, function (Blueprint $table) {
                $table->foreignId('reward_wallet_transaction_id')
                    ->nullable()
                    ->constrained('wallet_transactions')
                    ->nullOnDelete();
                $table->foreignId('account_reward_id')
                    ->nullable()
                    ->constrained('account_rewards')
                    ->nullOnDelete();
                $table->string('reward_failure_code', 64)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['character_reset_operations', 'character_grand_reset_operations'] as $operationTable) {
            Schema::table($operationTable, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reward_wallet_transaction_id');
                $table->dropConstrainedForeignId('account_reward_id');
                $table->dropColumn('reward_failure_code');
            });
        }
    }
};
