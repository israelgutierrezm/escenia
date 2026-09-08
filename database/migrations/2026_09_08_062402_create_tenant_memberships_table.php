<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge between users and tenants. A user may belong to many tenants without
 * being duplicated. This table is intentionally NOT globally tenant-scoped: it
 * is the mechanism used to resolve which tenants a user may access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');   // owner|admin|member
            $table->string('status')->default('active'); // invited|active|suspended
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
