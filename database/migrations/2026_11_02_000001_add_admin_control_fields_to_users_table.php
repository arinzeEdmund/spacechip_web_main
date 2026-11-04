<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_admin');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
            $table->timestamp('last_login_at')->nullable()->after('suspension_reason');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->index('suspended_at', 'users_suspended_at_idx');
            $table->index('last_login_at', 'users_last_login_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_suspended_at_idx');
            $table->dropIndex('users_last_login_at_idx');
            $table->dropColumn(['suspended_at', 'suspension_reason', 'last_login_at', 'last_login_ip']);
        });
    }
};
