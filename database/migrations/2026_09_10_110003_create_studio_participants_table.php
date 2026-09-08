<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A participant in a studio session, with a guarded stage lifecycle
 * (invited → green room → backstage → stage → left). May be a platform user or
 * a guest that redeemed a guest link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_participants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('studio_session_id')->constrained('studio_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guest_link_id')->nullable()->constrained('studio_guest_links')->nullOnDelete();
            $table->string('identity'); // media identity, unique within the session
            $table->string('name');
            $table->string('role')->default('guest');
            $table->string('stage')->default('invited')->index();
            $table->boolean('device_checked')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['studio_session_id', 'identity']);
            $table->index(['studio_session_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_participants');
    }
};
