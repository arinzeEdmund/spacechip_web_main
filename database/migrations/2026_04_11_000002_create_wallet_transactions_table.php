<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 8);
            $table->string('action', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('balance_before_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->string('currency', 8)->default('USD');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'wallet_tx_user_created_idx');
            $table->index(['action', 'created_at'], 'wallet_tx_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
