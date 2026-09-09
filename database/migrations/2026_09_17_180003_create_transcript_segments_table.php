<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A time-coded segment of a transcript.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained('transcripts')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->string('speaker')->nullable();
            $table->text('text');
            $table->timestamps();

            $table->index(['transcript_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
    }
};
