<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proof of what each person agreed to, which version, when and from where.
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40); // terms | privacy | withdrawal_waiver | cookies | marketing
            $table->string('version', 32)->nullable();
            $table->boolean('granted')->default(true);
            $table->string('source', 64)->nullable(); // web_register | mobile_register | checkout | ...
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'type']);
        });

        // Data-subject requests (access, deletion, correction, ...) with a legal deadline.
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 255);
            $table->string('type', 24); // access | delete | correct | restrict | object | other
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open'); // open | verifying | completed | rejected
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('consent_records');
    }
};
