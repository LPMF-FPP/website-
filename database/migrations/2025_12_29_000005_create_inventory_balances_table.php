<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->decimal('on_hand_qty', 14, 3)->default(0);
            $table->decimal('reserved_qty', 14, 3)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['item_id', 'lot_id', 'location_id'], 'inventory_balances_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
