<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_number_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_number_subscriptions', 'billing_method')) {
                $table->string('billing_method', 16)->default('paystack')->after('provider');
                $table->index(['billing_method', 'status'], 'vn_sub_bill_status_idx');
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
                $table->dropIndex('vn_sub_bill_status_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropColumn('billing_method');
            } catch (Throwable) {
            }
        });
    }
};
