<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The composition mechanism: which capabilities an event has turned on, and
 * their per-capability settings. Enabling a capability is gated by the tenant's
 * plan entitlements (ADR-013 / ADR-016).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_capabilities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('capability');
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_capabilities');
    }
};
