<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The destinations a broadcast is pushing to (multistream), with per-destination
 * status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_destinations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('broadcast_session_id')->constrained('broadcast_sessions')->cascadeOnDelete();
            $table->foreignId('stream_destination_id')->constrained('stream_destinations')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|live|failed
            $table->timestamps();

            $table->unique(['broadcast_session_id', 'stream_destination_id'], 'broadcast_destination_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_destinations');
    }
};
