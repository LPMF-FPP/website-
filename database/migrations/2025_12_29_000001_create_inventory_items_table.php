<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->enum('item_type', ['REAGENT', 'CONSUMABLE', 'STANDARD', 'CONTROL', 'OTHER']);
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->text('specification')->nullable();
            $table->string('uom'); // mL, g, pcs
            $table->decimal('pack_size', 12, 3)->nullable();
            $table->boolean('is_hazardous')->default(false);
            $table->string('hazard_class')->nullable();
            $table->string('storage_condition')->nullable(); // RT, 2-8C, -20C
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_type', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
