<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speakers attached to an event. A speaker may optionally link to a platform
 * user (user_id) or be a purely external/invited person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_speakers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('role')->default('speaker');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_speakers');
    }
};
