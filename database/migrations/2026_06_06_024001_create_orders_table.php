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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_event_id')->constrained()->restrictOnDelete();

            $table->string('order_no')->unique();
            $table->enum('status', [
                'pending',
                'confirmed',
                'paid',
                'cancelled',
                'failed',
                'expired',
            ])->default('pending');

            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('ordered_at');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'product_id', 'sales_event_id']);

            $table->index(['sales_event_id', 'product_id', 'status']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['ordered_at']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
