<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_number_subscriptions', 'paystack_email')) {
                $table->string('paystack_email', 255)->nullable()->after('paystack_authorization_code');
                $table->index(['paystack_email'], 'vn_sub_paystack_email_idx');
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
                $table->dropIndex('vn_sub_paystack_email_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropColumn('paystack_email');
            } catch (Throwable) {
            }
        });
    }
};
