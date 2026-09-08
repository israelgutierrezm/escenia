<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the vision-mixer state to a studio: the staged (preview) scene and the
 * on-air (program) scene. Additive, nullable columns — zero-downtime safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->foreignId('preview_scene_id')->nullable()->after('provider')->constrained('scenes')->nullOnDelete();
            $table->foreignId('program_scene_id')->nullable()->after('preview_scene_id')->constrained('scenes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preview_scene_id');
            $table->dropConstrainedForeignId('program_scene_id');
        });
    }
};
