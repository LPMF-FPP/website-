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
        // For PostgreSQL, we need to alter the check constraint
        DB::statement('ALTER TABLE whatsapp_command_logs DROP CONSTRAINT IF EXISTS whatsapp_command_logs_response_status_check');
        DB::statement("ALTER TABLE whatsapp_command_logs ADD CONSTRAINT whatsapp_command_logs_response_status_check CHECK (response_status::text = ANY (ARRAY['processing'::text, 'success'::text, 'error'::text, 'invalid'::text, 'unknown_command'::text, 'ignored'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE whatsapp_command_logs DROP CONSTRAINT IF EXISTS whatsapp_command_logs_response_status_check');
        DB::statement("ALTER TABLE whatsapp_command_logs ADD CONSTRAINT whatsapp_command_logs_response_status_check CHECK (response_status::text = ANY (ARRAY['processing'::text, 'success'::text, 'error'::text, 'invalid'::text, 'unknown_command'::text]))");
    }
};
