<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'received' to the enum
        DB::statement('ALTER TABLE whatsapp_command_logs DROP CONSTRAINT IF EXISTS whatsapp_command_logs_response_status_check');
        DB::statement("ALTER TABLE whatsapp_command_logs ADD CONSTRAINT whatsapp_command_logs_response_status_check CHECK (response_status::text = ANY (ARRAY['processing'::text, 'success'::text, 'error'::text, 'invalid'::text, 'unknown_command'::text, 'ignored'::text, 'received'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We generally can't remove enum values if data exists, but for revert:
        DB::statement('ALTER TABLE whatsapp_command_logs DROP CONSTRAINT IF EXISTS whatsapp_command_logs_response_status_check');
        DB::statement("ALTER TABLE whatsapp_command_logs ADD CONSTRAINT whatsapp_command_logs_response_status_check CHECK (response_status::text = ANY (ARRAY['processing'::text, 'success'::text, 'error'::text, 'invalid'::text, 'unknown_command'::text, 'ignored'::text]))");
    }
};
