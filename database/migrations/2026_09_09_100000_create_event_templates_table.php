<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event templates seed an event's default capabilities and settings. A null
 * tenant_id marks a system template available to every tenant; a set tenant_id
 * marks a tenant-owned template. Not blanket tenant-scoped for that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // EventType
            $table->text('description')->nullable();
            $table->json('default_capabilities')->nullable(); // list<string> of capability keys
            $table->json('default_settings')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
