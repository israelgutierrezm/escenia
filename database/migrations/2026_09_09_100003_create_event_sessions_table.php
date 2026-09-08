<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
