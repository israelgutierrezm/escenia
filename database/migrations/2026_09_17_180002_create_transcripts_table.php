<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transcript of a recording, produced by a transcription provider (behind the
 * Transcriber contract) in a background job. Segments live in a child table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->string('provider');
            $table->string('language', 12)->default('en');
            $table->string('status')->default('pending');
            $table->string('failed_reason')->nullable();
            $table->timestamps();

            $table->index('recording_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
