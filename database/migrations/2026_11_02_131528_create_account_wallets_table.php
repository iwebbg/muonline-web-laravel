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
        Schema::create('account_wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('account_id', 10);
            $table->string('currency', 32);
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_wallets');
    }
};
