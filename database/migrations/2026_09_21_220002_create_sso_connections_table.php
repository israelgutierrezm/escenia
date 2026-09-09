<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's SSO connection (OIDC/SAML). The provider-specific config (client
 * secret, certificates, endpoints) is encrypted at rest and never serialized or
 * logged. The email domain lets us route a login hint to the right connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_connections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider');                    // oidc|saml
            $table->string('display_name');
            $table->string('domain')->nullable();          // email domain, e.g. acme.com
            $table->text('config');                        // encrypted json (secrets, endpoints)
            $table->string('default_role')->default('member'); // tenant role for provisioned users
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_connections');
    }
};
