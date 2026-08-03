<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('http_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('trace_id', 36)->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('service', 100);
            $table->string('method', 10);
            $table->text('url');
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->string('ip_address', 45)->nullable();   // supports IPv6
            $table->decimal('duration_ms', 10, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at');

            // Indexes for common queries
            $table->index('trace_id');          // find all logs for one request
            $table->index('service');
            $table->index('response_status');   // find all 500s
            $table->index('user_id');           // find all requests by a user
            $table->index('created_at');        // time-range queries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('http_logs');
    }
};