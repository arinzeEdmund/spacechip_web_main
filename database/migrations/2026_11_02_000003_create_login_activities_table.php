<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('channel', 16);          // web | api | social
            $table->boolean('successful')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at'], 'login_user_created_idx');
            $table->index(['successful', 'created_at'], 'login_success_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};
