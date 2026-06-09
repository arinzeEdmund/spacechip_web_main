<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_number_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 32)->default('smspva_rent');
            $table->string('provider_order_id', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('product', 64);
            $table->string('product_name', 128);
            $table->string('service_code', 32);
            $table->string('country', 16);
            $table->string('country_name', 128)->nullable();
            $table->string('provider_name', 64)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('phone_country_code', 16)->nullable();
            $table->unsignedBigInteger('provider_cost_minor')->default(0);
            $table->unsignedBigInteger('monthly_amount_minor')->default(0);
            $table->string('currency', 8)->default('USD');
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_sms_sync_at')->nullable();
            $table->timestamp('last_renewed_at')->nullable();
            $table->unsignedInteger('renewal_failed_count')->default(0);
            $table->string('last_renewal_error', 255)->nullable();
            $table->string('last_charge_reference', 128)->nullable();
            $table->json('sms_messages')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'social_rentals_user_status_idx');
            $table->index(['status', 'current_period_end'], 'social_rentals_status_period_idx');
            $table->unique(['provider', 'provider_order_id'], 'social_rentals_provider_order_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_number_rentals');
    }
};
