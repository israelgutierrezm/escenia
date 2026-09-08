<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable versions of a scene's definition. Each save appends a version;
 * `is_current` marks the active one. `schema_version` tracks the definition
 * format so old designs can still be opened after migration (ADR-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained('scenes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('schema_version');
            $table->json('definition');
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scene_id', 'version']);
            $table->index(['scene_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_versions');
    }
};
