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
        Schema::create('game_account_password_resets', function (Blueprint $table): void {
            $table->id();
            $table->string('account_id', 10)->index();
            $table->char('token_hash', 64)->unique();
            $table->char('email_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();

            $table->index(['account_id', 'used_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_account_password_resets');
    }
};
