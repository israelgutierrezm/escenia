<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only gamification ledger: one row per points-earning action by an
 * attendee. Unique per (attendee, action, subject) keeps awards idempotent so an
 * action is not double-counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_awards', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('action');
            $table->string('subject')->nullable(); // e.g. the session/booth ULID
            $table->unsignedInteger('points');
            $table->timestamp('created_at')->nullable();

            $table->unique(['attendee_id', 'action', 'subject'], 'points_award_unique');
            $table->index(['event_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_awards');
    }
};
