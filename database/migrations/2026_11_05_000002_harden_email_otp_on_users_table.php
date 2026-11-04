<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_otp', 64)->nullable()->change(); // now stores a keyed hash, never the code itself
            $table->unsignedTinyInteger('email_otp_attempts')->default(0)->after('email_otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_otp_attempts');
        });
    }
};
