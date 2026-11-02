<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates immutable administrator actions for custody and bank recovery.
     */
    public function up(): void
    {
        Schema::create('economy_recovery_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('subject_type', 32)->index();
            $table->uuid('subject_public_id')->index();
            $table->string('account_id', 10)->index();
            $table->string('action', 40);
            $table->string('before_status', 24);
            $table->string('after_status', 24);
            $table->string('outcome', 24)->index();
            $table->string('failure_code', 80)->nullable();
            $table->json('context_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Removes economy recovery audit actions.
     */
    public function down(): void
    {
        Schema::dropIfExists('economy_recovery_actions');
    }
};
