<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A SAML connection's own service-provider credentials (ADR-036): a
 * self-signed certificate (public, published in the SP metadata) and its
 * private key (encrypted at rest), used to sign AuthnRequests/logout messages
 * and decrypt encrypted assertions. Kept out of the tenant-editable `config`,
 * which an update replaces wholesale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->text('sp_certificate')->nullable()->after('config');
            $table->text('sp_private_key')->nullable()->after('sp_certificate'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->dropColumn(['sp_certificate', 'sp_private_key']);
        });
    }
};
