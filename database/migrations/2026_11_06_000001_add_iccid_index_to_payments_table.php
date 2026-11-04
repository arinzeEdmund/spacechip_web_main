<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Blind index (keyed hash) of the eSIM's ICCID so we can find an order from a provider webhook
            // while the delivered activation data itself stays encrypted.
            $table->string('iccid_index', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('iccid_index');
        });
    }
};
