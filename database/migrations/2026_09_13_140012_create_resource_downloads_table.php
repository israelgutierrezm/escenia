<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per attendee download, so counts stay idempotent per attendee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_downloads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['resource_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_downloads');
    }
};
