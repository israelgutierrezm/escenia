<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable streaming destinations (RTMP/RTMPS/SRT) scoped to a workspace. The
 * stream key is a secret: stored encrypted at rest and never exposed by the API
 * (CLAUDE.md / security.md). `text` holds the ciphertext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_destinations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('protocol')->default('rtmp'); // rtmp|rtmps|srt
            $table->string('url');
            $table->text('stream_key'); // encrypted at rest
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_destinations');
    }
};
