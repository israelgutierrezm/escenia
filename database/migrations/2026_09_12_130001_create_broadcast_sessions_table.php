<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A broadcast run of a studio session: egress to one or more destinations, with
 * a guarded lifecycle and a health state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('studio_session_id')->constrained('studio_sessions')->cascadeOnDelete();
            $table->string('status')->default('idle')->index(); // idle|starting|live|ended|failed
            $table->string('health')->default('unknown');       // unknown|healthy|degraded|failed
            $table->boolean('record')->default(false);
            $table->string('egress_ref')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['studio_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_sessions');
    }
};
