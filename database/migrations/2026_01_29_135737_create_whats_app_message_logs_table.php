<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('whatsapp_message_batches')->onDelete('cascade');
            $table->string('recipient_jid', 100);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_type', 20)->nullable(); // 'individual', 'group'
            $table->string('status', 50)->default('pending');
            $table->text('error_message')->nullable();
            $table->string('message_id', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
