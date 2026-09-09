<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's custom (white-label) domain. Ownership is proven with a DNS TXT
 * challenge before the domain can route traffic. The hostname is globally
 * unique: one domain maps to at most one tenant (and optionally a workspace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('hostname')->unique();            // e.g. events.acme.com
            $table->string('status')->default('pending');    // pending|active|failed
            $table->string('verification_token', 64);        // DNS TXT challenge value
            $table->string('target')->nullable();            // CNAME target the tenant should point at
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
