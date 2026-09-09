<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AI-generated artifact for a recording (Content Factory): a summary, chapter
 * list, or highlights. Produced by a completion provider in a background job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_summaries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->string('kind'); // summary|chapters|highlights
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->longText('content')->nullable();
            $table->string('failed_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recording_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_summaries');
    }
};
