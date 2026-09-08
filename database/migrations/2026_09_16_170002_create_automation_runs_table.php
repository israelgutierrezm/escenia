<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single execution of an automation for one trigger occurrence. `context` is
 * the flattened snapshot conditions/steps read from. Unique per (automation,
 * outbox_event_id) so an at-least-once trigger never starts a duplicate run.
 * `resume_at` drives the sequence: a wait step parks the run for later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('outbox_event_id')->nullable()->constrained('outbox_events')->nullOnDelete();
            $table->string('trigger');
            $table->json('context')->nullable();
            $table->string('status')->default('running'); // AutomationRunStatus value
            $table->unsignedInteger('current_position')->default(0);
            $table->timestamp('resume_at')->nullable();
            $table->json('log')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'outbox_event_id']);
            $table->index(['status', 'resume_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
