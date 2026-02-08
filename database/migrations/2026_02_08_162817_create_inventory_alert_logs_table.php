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
        Schema::create('inventory_alert_logs', function (Blueprint $table) {
            $table->id();

            $table->string('alert_type', 30);
            $table->foreignId('item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();

            $table->text('message');
            $table->json('recipients');
            $table->json('sent_to')->nullable();
            $table->json('failed_to')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['alert_type', 'created_at']);
            $table->index('item_id');
            $table->index('lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_alert_logs');
    }
};
