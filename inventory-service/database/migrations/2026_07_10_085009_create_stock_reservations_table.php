<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id'); // uuid from order-service, no FK (cross-service)
            $table->string('sku');
            $table->unsignedInteger('quantity');
            $table->enum('status', ['held', 'committed', 'released', 'expired'])->default('held');
            $table->timestamp('expires_at'); // 15-minute TTL
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['sku', 'status']);
            $table->index('expires_at'); // for ReleaseExpiredReservationsJob
            $table->unique(['order_id', 'sku']); // one reservation per sku per order
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
