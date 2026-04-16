<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_number_messages')) {
            return;
        }

        Schema::table('virtual_number_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('virtual_number_messages', 'message_type')) {
                $table->string('message_type', 16)->default('sms')->after('direction');
                $table->index(['message_type', 'created_at'], 'vn_msg_type_created_idx');
            }
            if (! Schema::hasColumn('virtual_number_messages', 'media')) {
                $table->json('media')->nullable()->after('twilio_message_sid');
            }
            if (! Schema::hasColumn('virtual_number_messages', 'twilio_call_sid')) {
                $table->string('twilio_call_sid', 64)->nullable()->after('media');
                $table->index(['twilio_call_sid'], 'vn_msg_call_sid_idx');
            }
            if (! Schema::hasColumn('virtual_number_messages', 'twilio_recording_sid')) {
                $table->string('twilio_recording_sid', 64)->nullable()->after('twilio_call_sid');
                $table->unique(['twilio_recording_sid'], 'vn_msg_recording_sid_uq');
            }
            if (! Schema::hasColumn('virtual_number_messages', 'recording_url')) {
                $table->text('recording_url')->nullable()->after('twilio_recording_sid');
            }
            if (! Schema::hasColumn('virtual_number_messages', 'recording_duration')) {
                $table->unsignedInteger('recording_duration')->nullable()->after('recording_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('virtual_number_messages')) {
            return;
        }

        Schema::table('virtual_number_messages', function (Blueprint $table) {
            try {
                $table->dropIndex('vn_msg_type_created_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropIndex('vn_msg_call_sid_idx');
            } catch (Throwable) {
            }
            try {
                $table->dropUnique('vn_msg_recording_sid_uq');
            } catch (Throwable) {
            }
            try {
                $table->dropColumn(['message_type', 'media', 'twilio_call_sid', 'twilio_recording_sid', 'recording_url', 'recording_duration']);
            } catch (Throwable) {
            }
        });
    }
};
