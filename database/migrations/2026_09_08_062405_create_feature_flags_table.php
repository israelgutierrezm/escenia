<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight feature flag store. Supports evolution to global -> tenant ->
 * workspace -> user targeting plus percentage rollout, without building a full
 * experimentation platform (see ADR-013). Global-scope uniqueness is enforced
 * at the application layer because MySQL treats NULLs as distinct in a unique
 * index (documented in technical-debt.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->index();
            $table->string('scope')->default('global'); // global|tenant|workspace|user
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->nullable(); // 0..100
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(
                ['key', 'scope', 'tenant_id', 'workspace_id', 'user_id'],
                'feature_flags_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
