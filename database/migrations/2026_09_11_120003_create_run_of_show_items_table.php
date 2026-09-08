<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The studio rundown: an ordered list of segments, each optionally taking a
 * scene, with a planned duration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_of_show_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['studio_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_of_show_items');
    }
};
