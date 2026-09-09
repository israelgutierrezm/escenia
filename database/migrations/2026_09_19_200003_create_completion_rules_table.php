<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rule an attendee must meet to be certified for an event: a minimum watched
 * time and/or passing the published assessment. One rule per event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completion_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('min_watch_seconds')->nullable();
            $table->boolean('require_assessment')->default(false);
            $table->timestamps();

            $table->unique('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_rules');
    }
};
