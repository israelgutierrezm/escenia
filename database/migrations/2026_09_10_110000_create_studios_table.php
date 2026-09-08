<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The studio: the operational control room for producing an event. Laravel
 * orchestrates it; the media plane transports the media (ADR-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('idle')->index(); // idle|live|ended
            $table->string('provider')->default('fake');
            $table->json('settings')->nullable();
            $table->timestamps();

            // At most one studio per event; standalone studios have null event_id.
            $table->unique('event_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studios');
    }
};
