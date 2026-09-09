<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A question on an assessment. `options` holds the choices, each with a
 * `correct` flag used only for server-side scoring (never sent to attendees).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('prompt');
            $table->string('type'); // single_choice|multiple_choice|true_false
            $table->unsignedInteger('points')->default(1);
            $table->json('options');
            $table->timestamps();

            $table->index(['assessment_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
