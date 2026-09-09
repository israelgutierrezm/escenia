<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An embedded chunk of a transcript — the searchable index for semantic replay
 * and RAG (ADR-027). `vector` is the embedding; `event_id` is denormalized so
 * search can be scoped without a join. The default vector index does cosine over
 * this table; Qdrant is the production adapter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_chunks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained('transcripts')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->text('text');
            $table->json('vector');
            $table->timestamps();

            $table->index(['event_id', 'id']);
            $table->index('transcript_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_chunks');
    }
};
