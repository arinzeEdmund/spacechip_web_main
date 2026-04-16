<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wallet_balance_minor')) {
                $table->unsignedBigInteger('wallet_balance_minor')->default(0)->after('remember_token');
                $table->index(['wallet_balance_minor'], 'users_wallet_balance_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropIndex('users_wallet_balance_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropColumn('wallet_balance_minor');
            } catch (Throwable) {
            }
        });
    }
};
