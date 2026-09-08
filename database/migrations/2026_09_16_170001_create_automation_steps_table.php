<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One ordered step of an automation. `config` holds the type-specific settings
 * (webhook url, tag, notification template, wait seconds); `conditions` gate the
 * step (conditional logic / branching).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type'); // AutomationStepType value
            $table->json('config')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_steps');
    }
};
