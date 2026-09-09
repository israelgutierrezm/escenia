<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A graded assessment (quiz) for an event. `passing_score` is a percentage.
 * Only published assessments are visible to attendees (ADR-028).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
