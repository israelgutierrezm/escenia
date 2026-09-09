<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recording asset for an event: produced from a broadcast (cloud egress) or
 * uploaded (local recording). The binary lives in object storage (disk +
 * storage_key); Laravel never receives it (ADR-026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('broadcast_session_id')->nullable()->constrained('broadcast_sessions')->nullOnDelete();
            $table->string('source'); // broadcast|upload
            $table->string('status')->default('pending'); // pending|processing|ready|failed
            $table->string('title')->nullable();
            $table->string('disk')->nullable();
            $table->string('storage_key')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('format')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
