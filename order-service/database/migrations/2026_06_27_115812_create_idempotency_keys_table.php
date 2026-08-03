<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency keys table — durable fallback layer.
     *
     * Redis is the fast path for idempotency checks (sub-millisecond).
     * This table is the durable fallback — if Redis flushes or restarts,
     * duplicate detection still works. On every request we write here AND
     * to Redis. On lookup we check Redis first; miss falls through to DB.
     *
     * Records are pruned by a scheduled job after 24 hours (same TTL as Redis).
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('request_method', 10);
            $table->string('request_path');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->json('response_headers')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('key');
            $table->index('user_id');
            $table->index('expires_at');         // for the pruning job
            $table->index(['key', 'request_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};