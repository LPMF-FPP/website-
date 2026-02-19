<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_command_logs', function (Blueprint $table) {
            $table->string('provider_message_id', 191)->nullable()->after('message_text');
            $table->string('message_fingerprint', 64)->nullable()->after('provider_message_id');
            $table->timestamp('processed_at')->nullable()->after('response_text');

            $table->index('provider_message_id', 'wa_cmd_logs_provider_msg_idx');
            $table->index('message_fingerprint', 'wa_cmd_logs_fingerprint_idx');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS wa_cmd_logs_provider_msg_unique ON whatsapp_command_logs (provider_message_id) WHERE provider_message_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS wa_cmd_logs_fingerprint_unique ON whatsapp_command_logs (message_fingerprint) WHERE message_fingerprint IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wa_cmd_logs_provider_msg_unique');
        DB::statement('DROP INDEX IF EXISTS wa_cmd_logs_fingerprint_unique');

        Schema::table('whatsapp_command_logs', function (Blueprint $table) {
            $table->dropIndex('wa_cmd_logs_provider_msg_idx');
            $table->dropIndex('wa_cmd_logs_fingerprint_idx');

            $table->dropColumn([
                'provider_message_id',
                'message_fingerprint',
                'processed_at',
            ]);
        });
    }
};
