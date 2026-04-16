<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_number_subscriptions')) {
            Schema::create('virtual_number_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('virtual_number_product_id')->constrained()->cascadeOnDelete();

                $table->string('status', 24)->default('pending');
                $table->string('phone_number', 32)->nullable();
                $table->string('country_iso', 2)->nullable();
                $table->boolean('cap_sms')->default(true);
                $table->boolean('cap_voice')->default(true);

                $table->string('provider', 24)->default('twilio');
                $table->string('twilio_phone_number_sid', 64)->nullable();

                $table->string('currency', 8)->default('USD');
                $table->unsignedBigInteger('monthly_amount_minor')->default(0);
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();

                $table->string('paystack_customer_code', 64)->nullable();
                $table->string('paystack_authorization_code', 64)->nullable();
                $table->string('last_charge_reference', 128)->nullable();
                $table->unsignedInteger('renewal_failed_count')->default(0);
                $table->string('last_renewal_error', 255)->nullable();

                $table->timestamp('canceled_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['status', 'current_period_end']);
                $table->unique(['provider', 'twilio_phone_number_sid'], 'vn_sub_provider_twilio_sid_uq');
            });

            return;
        }

        try {
            Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
                $table->index(['user_id', 'status']);
            });
        } catch (Throwable) {
        }

        try {
            Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
                $table->index(['status', 'current_period_end']);
            });
        } catch (Throwable) {
        }

        try {
            Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
                $table->unique(['provider', 'twilio_phone_number_sid'], 'vn_sub_provider_twilio_sid_uq');
            });
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_number_subscriptions');
    }
};
