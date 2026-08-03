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
        Schema::create('inventory_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type')->default('stock_reservation');
            $table->string('aggregate_id'); // stock_reservation.id or order_id
            $table->string('event_type');   // stock.reserved | stock.insufficient | stock.released
            $table->json('payload');
            $table->string('routing_key');  // e.g. stock.reserved
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['published', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_outbox');
    }
};
