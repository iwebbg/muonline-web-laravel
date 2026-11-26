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
        Schema::create('manual_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_payment_id')->unique()->constrained()->noActionOnDelete();
            $table->foreignId('administrator_id')->constrained()->noActionOnDelete();
            $table->string('reference', 128)->unique();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_payment_receipts');
    }
};
