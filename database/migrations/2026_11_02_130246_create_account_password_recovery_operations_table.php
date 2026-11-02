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
        Schema::create('account_password_recovery_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('account_id', 10)->index();
            $table->string('operation_type', 32)->index();
            $table->string('status', 32)->index();
            $table->json('settings_snapshot');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'operation_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_password_recovery_operations');
    }
};
