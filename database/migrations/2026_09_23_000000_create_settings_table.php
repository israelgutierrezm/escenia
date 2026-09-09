<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime configuration store (ADR-033). Any external-API credential or setting
 * in the catalog can be configured from within the system instead of only via
 * environment variables. Rows are scoped `system` (platform-wide) or `tenant`,
 * and the value is encrypted at rest (it may hold a secret). Resolution
 * precedence is tenant → system → config/env default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('scope', 16);              // system|tenant
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->text('value');                    // encrypted JSON
            $table->timestamps();

            // Uniqueness of system rows (tenant_id NULL) is enforced in the
            // repository upsert (MySQL treats multiple NULLs as distinct).
            $table->unique(['scope', 'tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
