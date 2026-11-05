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
        Schema::create('account_rewards', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('account_id', 10)->index();
            $table->foreignId('shop_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->string('source_reference')->nullable();
            $table->foreignId('granted_by_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->string('source_reason', 500)->nullable();
            $table->string('status', 32);
            $table->string('reward_type', 32);
            $table->string('class_scope', 32)->default('all');
            $table->string('title_en', 120);
            $table->string('title_bg', 120);
            $table->text('description_en')->nullable();
            $table->text('description_bg')->nullable();
            $table->json('reward_pool_snapshot');
            $table->json('selected_item_snapshot')->nullable();
            $table->unsignedInteger('item_serial')->nullable();
            $table->binary('item_binary')->nullable();
            $table->char('item_fingerprint', 64)->nullable()->index();
            $table->string('delivery_destination', 32)->nullable();
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->timestamp('last_delivery_attempt_at')->nullable();
            $table->string('last_delivery_error', 80)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status', 'created_at']);
            $table->unique(['source', 'source_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_rewards');
    }
};
