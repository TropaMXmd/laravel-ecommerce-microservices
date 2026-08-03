<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbox table — the Transactional Outbox Pattern.
     *
     * Problem: if we write an order to DB and then publish to RabbitMQ,
     * there is a window where the DB write succeeds but the publish fails.
     * The order exists but the Inventory Service never hears about it.
     *
     * Solution: write the order AND an outbox record in the SAME DB transaction.
     * A background job (PublishOutboxMessagesJob) polls this table every minute,
     * publishes unpublished records to RabbitMQ, and marks them published.
     *
     * This guarantees at-least-once delivery. Consumers must be idempotent.
     */
    public function up(): void
    {
        Schema::create('order_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();              // idempotency key for consumers
            $table->string('aggregate_type');                // e.g. 'Order'
            $table->unsignedBigInteger('aggregate_id');      // order.id
            $table->string('event_type');                    // e.g. 'order.placed'
            $table->string('exchange');                      // RabbitMQ exchange name
            $table->string('routing_key');                   // RabbitMQ routing key
            $table->json('payload');                         // full event payload
            $table->json('headers')->nullable();             // optional AMQP headers
            $table->enum('status', ['pending', 'published', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error')->nullable();             // last error if failed
            $table->timestamp('published_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('aggregate_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_outbox');
    }
};