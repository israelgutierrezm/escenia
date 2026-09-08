<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signed guest invitation to a studio. The raw token is shown once to the
 * inviter; only its hash is stored. Supports expiry, single-use / max-uses and
 * revocation (security.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_guest_links', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('studio_id')->constrained('studios')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash')->unique();
            $table->string('name');
            $table->string('role')->default('guest');
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('single_use')->default(false);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_guest_links');
    }
};
