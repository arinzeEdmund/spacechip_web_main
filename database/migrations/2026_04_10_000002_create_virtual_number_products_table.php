<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_number_products', function (Blueprint $table) {
            $table->id();
            $table->string('country_iso', 2);
            $table->string('label', 64);
            $table->boolean('cap_sms')->default(true);
            $table->boolean('cap_voice')->default(true);
            $table->string('currency', 8)->default('USD');
            $table->unsignedBigInteger('monthly_amount_minor');
            $table->json('twilio_search_filters')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['country_iso', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_number_products');
    }
};
