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
        Schema::create('online_time_accounts', function (Blueprint $table) {
            $table->string('account_id', 10)->primary();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('accounted_until')->nullable();
            $table->timestamp('last_observed_at')->nullable()->index();
            $table->date('reward_date')->nullable();
            $table->unsignedInteger('rewarded_seconds_today')->default(0);
            $table->unsignedBigInteger('rewarded_credits_today')->default(0);
            $table->unsignedInteger('remainder_seconds')->default(0);
            $table->unsignedBigInteger('claimable_credits')->default(0);
            $table->unsignedBigInteger('lifetime_seconds')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_time_accounts');
    }
};
