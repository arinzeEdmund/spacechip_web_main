<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_number_orders', function (Blueprint $table) {
            $table->text('sms_code')->nullable()->change(); // ciphertext is longer than the 6-8 digit code
        });
    }

    public function down(): void
    {
        // Left wide on purpose: shrinking would truncate ciphertext.
    }
};
