<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Event aggregate — the source of truth for an event and its lifecycle.
 * Behaviour is composed from capabilities (see event_capabilities); the `type`
 * is a preset label, never a table tree (ADR-016). Status is driven by a guarded
 * state machine (ADR-017).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('event_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('draft')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            // Slugs are unique per workspace.
            $table->unique(['workspace_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
