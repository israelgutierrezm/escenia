<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox (ADR-007). Critical events are written here inside the
 * same DB transaction that changes state, then published at-least-once by the
 * dispatcher. Consumers must be idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('topic');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // The dispatcher scans for unprocessed, due rows in insertion order.
            $table->index(['processed_at', 'available_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
