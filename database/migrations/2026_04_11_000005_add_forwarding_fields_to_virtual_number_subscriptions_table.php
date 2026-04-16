<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_number_subscriptions')) {
            return;
        }

        Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_number_subscriptions', 'forward_to_email')) {
                $table->string('forward_to_email', 255)->nullable()->after('paystack_email');
                $table->index(['forward_to_email'], 'vn_sub_forward_email_idx');
            }
            if (! Schema::hasColumn('virtual_number_subscriptions', 'forward_to_phone')) {
                $table->string('forward_to_phone', 32)->nullable()->after('forward_to_email');
                $table->index(['forward_to_phone'], 'vn_sub_forward_phone_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('virtual_number_subscriptions')) {
            return;
        }

        Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
            try {
                $table->dropIndex('vn_sub_forward_email_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropIndex('vn_sub_forward_phone_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropColumn(['forward_to_email', 'forward_to_phone']);
            } catch (Throwable) {
            }
        });
    }
};
