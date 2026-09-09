<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An isolated (ISO) track of a recording — the composite program or a per-source
 * isolation (screen/camera/audio) for post-production. Each track is its own
 * object in storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_tracks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->string('kind'); // composite|screen|camera|audio
            $table->string('label')->nullable();
            $table->string('status')->default('pending');
            $table->string('disk')->nullable();
            $table->string('storage_key')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index('recording_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_tracks');
    }
};
