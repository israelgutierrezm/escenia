<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant automation: a trigger + top-level conditions + an ordered sequence of
 * steps (in `automation_steps`). Reacts to committed system events (ADR-025).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger'); // TriggerEvent value
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
