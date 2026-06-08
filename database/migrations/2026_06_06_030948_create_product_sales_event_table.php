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
        Schema::create('product_sales_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('event_stock_limit')->nullable();
            $table->decimal('event_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'sales_event_id']);
            $table->index(['sales_event_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sales_event');
    }
};
