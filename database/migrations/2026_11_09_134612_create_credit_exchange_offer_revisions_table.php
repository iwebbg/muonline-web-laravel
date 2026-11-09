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
        Schema::create('credit_exchange_offer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_exchange_offer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action', 30);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->foreignId('changed_by_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['credit_exchange_offer_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_exchange_offer_revisions');
    }
};
