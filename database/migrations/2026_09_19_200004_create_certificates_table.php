<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A certificate issued to an attendee who met an event's completion rule.
 * `code` is an opaque public identifier for verification. One per
 * (event, attendee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('recipient_name');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['event_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
