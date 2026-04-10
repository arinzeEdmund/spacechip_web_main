<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_reference', 128);
            $table->string('status', 32)->default('created');
            $table->string('asset_type', 32);
            $table->string('asset_id', 64);
            $table->string('bundle_id', 64);
            $table->string('package_type', 32);
            $table->string('currency', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->json('provider_payload')->nullable();
            $table->json('fulfillment_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

