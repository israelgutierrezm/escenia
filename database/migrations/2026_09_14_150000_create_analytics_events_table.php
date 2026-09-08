<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, versioned analytics stream (ADR-005). This is the analytics
 * source of truth, kept separate from the OLTP tables on purpose so it can be
 * migrated to ClickHouse behind the collector without touching operational
 * schema. Never mutated after insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            // Reporting reads by event + name over time; attendance timelines
            // sweep by attendee within an event.
            $table->index(['event_id', 'name', 'occurred_at']);
            $table->index(['event_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
