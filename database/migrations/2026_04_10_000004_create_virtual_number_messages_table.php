<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_number_messages')) {
            Schema::create('virtual_number_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('virtual_number_subscription_id')->constrained()->cascadeOnDelete();
                $table->string('direction', 16);
                $table->string('from', 32)->nullable();
                $table->string('to', 32)->nullable();
                $table->text('body')->nullable();
                $table->string('twilio_message_sid', 64)->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();

                $table->index(['virtual_number_subscription_id', 'created_at'], 'vn_msg_sub_created_idx');
                $table->unique(['twilio_message_sid'], 'vn_msg_twilio_sid_uq');
            });

            return;
        }

        try {
            Schema::table('virtual_number_messages', function (Blueprint $table) {
                $table->index(['virtual_number_subscription_id', 'created_at'], 'vn_msg_sub_created_idx');
            });
        } catch (Throwable) {
        }

        try {
            Schema::table('virtual_number_messages', function (Blueprint $table) {
                $table->unique(['twilio_message_sid'], 'vn_msg_twilio_sid_uq');
            });
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_number_messages');
    }
};
