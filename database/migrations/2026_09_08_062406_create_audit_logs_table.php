<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for security-relevant actions (auth, tenant/member
 * changes, permission changes, critical configuration). It intentionally does
 * NOT record everything, and NEVER stores secrets in `context` (see ADR and
 * AGENTS.md security rules). No updated_at: rows are immutable once written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable(); // snapshot, e.g. email or 'system'
            $table->string('action')->index();          // 'tenant.created', 'auth.login', ...
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('context')->nullable();         // safe metadata only
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
