<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('lot_no');
            $table->date('expiry_date')->nullable();
            $table->date('received_date')->nullable();
            $table->enum('status', ['ACTIVE', 'QUARANTINE', 'EXPIRED', 'DISPOSED'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index('expiry_date');
            $table->index('lot_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
    }
};
