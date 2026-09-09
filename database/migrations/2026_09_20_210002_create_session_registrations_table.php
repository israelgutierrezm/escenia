<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attendee's registration for a session (their personal agenda). Unique per
 * (session, attendee); capacity is enforced against the session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_registrations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_session_id', 'attendee_id'], 'session_registration_unique');
            $table->index('attendee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_registrations');
    }
};
