<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency keys backing store.
 *
 * The composite UNIQUE(user_id, idempotency_key) is the correctness lynchpin:
 * it lets the middleware perform an atomic first-writer-wins INSERT. Only one
 * concurrent request can win the insert; every other request collides on the
 * unique constraint and is routed to the replay / in-flight / conflict paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('idempotency_key', 255);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Atomic first-writer-wins guarantee (per user, per key).
            $table->unique(['user_id', 'idempotency_key']);

            // Supports TTL-based pruning of expired keys.
            $table->index('created_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
