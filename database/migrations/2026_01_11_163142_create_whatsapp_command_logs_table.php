<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_command_logs', function (Blueprint $table) {
            $table->id();
            $table->string('from_jid')->comment('Sender WhatsApp JID');
            $table->string('from_phone_e164')->comment('Normalized phone number in E164 format');
            $table->text('message_text')->comment('Original incoming message');
            $table->string('command')->nullable()->comment('Parsed command (e.g., /resi)');
            $table->json('params')->nullable()->comment('Command parameters');
            $table->enum('response_status', ['processing', 'success', 'error', 'invalid', 'unknown_command'])->default('processing');
            $table->text('response_text')->nullable()->comment('Bot response text');
            $table->timestamps();

            $table->index(['from_jid', 'created_at']);
            $table->index('command');
            $table->index('response_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_command_logs');
    }
};
