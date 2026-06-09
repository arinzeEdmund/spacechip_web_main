<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_number_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 32)->default('smspva');
            $table->string('provider_order_id', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('product', 64);
            $table->string('product_name', 128);
            $table->string('service_code', 32);
            $table->string('country', 16);
            $table->string('country_name', 128)->nullable();
            $table->string('operator', 64)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('sms_code', 64)->nullable();
            $table->text('sms_text')->nullable();
            $table->string('sms_sender', 128)->nullable();
            $table->unsignedBigInteger('provider_cost_minor')->default(0);
            $table->unsignedBigInteger('sell_amount_minor')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->json('provider_payload')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('sms_received_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'social_orders_user_created_idx');
            $table->index(['provider', 'provider_order_id'], 'social_orders_provider_id_idx');
            $table->index(['status', 'created_at'], 'social_orders_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_number_orders');
    }
};
