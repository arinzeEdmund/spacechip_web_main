<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Authenticator app (TOTP). Secret is stored encrypted (cast on the model).
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            // Emailed one-time code (hashed, expiring, attempt-limited). Works for any admin, enrolled or not.
            $table->string('admin_code_hash', 64)->nullable();
            $table->timestamp('admin_code_expires_at')->nullable();
            $table->unsignedTinyInteger('admin_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step', 'admin_code_hash', 'admin_code_expires_at', 'admin_code_attempts']);
        });
    }
};
