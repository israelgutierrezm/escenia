<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's programmatic API credential. The raw key is shown exactly once at
 * creation; only its SHA-256 hash is stored (same token-credential pattern as
 * guest links and attendee tokens). The short prefix is kept in clear for
 * display and for narrowing the hash lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('prefix', 16)->index();      // e.g. esk_live_ab12 (display + lookup narrowing)
            $table->string('token_hash', 64)->unique(); // sha256 of the full raw key
            $table->json('scopes');                      // list<string>, e.g. ["events.read"]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
