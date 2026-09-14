<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Networking (Enterprise Events, TD-033): attendee-to-attendee connections and
 * 1:1 meetings, both scoped to an event and guarded by a status lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('attendees')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('message', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'requester_id', 'addressee_id'], 'connection_pair_unique');
            $table->index(['event_id', 'addressee_id']);
            $table->index(['event_id', 'requester_id']);
        });

        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('proposer_id')->constrained('attendees')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('status')->default('proposed');
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);
            $table->string('topic', 300)->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('attendees')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'invitee_id']);
            $table->index(['event_id', 'proposer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('connections');
    }
};
