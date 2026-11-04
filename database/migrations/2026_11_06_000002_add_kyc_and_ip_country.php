<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_status', 16)->default('none'); // none | required | approved
            $table->timestamp('kyc_decided_at')->nullable();
            $table->string('kyc_note', 255)->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('ip_country', 2)->nullable(); // where the buyer was when they paid (tax evidence)
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['kyc_status', 'kyc_decided_at', 'kyc_note']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('ip_country'));
    }
};
