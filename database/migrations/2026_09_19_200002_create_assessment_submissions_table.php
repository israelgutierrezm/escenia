<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attendee's completed assessment: their answers, the computed score
 * (percentage) and pass flag. One submission per (assessment, attendee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedTinyInteger('score')->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['assessment_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_submissions');
    }
};
