<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Failed attempts of the step at `current_position` (ADR-038): a step that
 * failed transiently is attempted again later; the counter resets when the run
 * moves on. Additive (no `after()`, so MySQL can add it instantly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('step_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropColumn('step_attempts');
        });
    }
};
