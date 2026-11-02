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
        Schema::create('item_grant_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->foreignId('administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('account_id', 10)->index();
            $table->string('item_code', 8)->index();
            $table->string('item_name', 120);
            $table->unsignedBigInteger('serial')->nullable();
            $table->binary('item_binary')->nullable();
            $table->string('status', 32)->index();
            $table->string('reason', 500);
            $table->json('context_snapshot');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_grant_operations');
    }
};
