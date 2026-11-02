<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates immutable administrator market recovery records.
     */
    public function up(): void
    {
        Schema::create('market_admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->string('before_status', 24);
            $table->string('after_status', 24);
            $table->string('outcome', 16);
            $table->string('failure_code', 80)->nullable();
            $table->json('context_snapshot')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Removes administrator market recovery records.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_admin_actions');
    }
};
