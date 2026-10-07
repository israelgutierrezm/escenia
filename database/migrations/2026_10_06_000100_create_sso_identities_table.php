<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Federated identities (ADR-035): which local user an IdP subject is, per SSO
 * connection. One subject per user per connection, so a reassigned email (a new
 * subject) can never inherit the previous holder's account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_identities', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sso_connection_id')->constrained('sso_connections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');                     // the IdP's stable user id (OIDC sub / SAML NameID)
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['sso_connection_id', 'subject']);
            $table->unique(['sso_connection_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_identities');
    }
};
