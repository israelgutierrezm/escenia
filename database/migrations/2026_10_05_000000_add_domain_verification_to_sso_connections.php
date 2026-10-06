<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * An SSO connection may only vouch for emails inside a domain the tenant has
 * proven it controls (DNS TXT challenge, ADR-034). Existing connections with a
 * domain get a challenge token and start unverified: their logins fail closed
 * until the tenant verifies the domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->string('domain_verification_token', 64)->nullable()->after('domain');
            $table->timestamp('domain_verified_at')->nullable()->after('domain_verification_token');
        });

        DB::table('sso_connections')
            ->whereNotNull('domain')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('sso_connections')->where('id', $row->id)->update([
                    'domain_verification_token' => Str::lower(Str::random(40)),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->dropColumn(['domain_verification_token', 'domain_verified_at']);
        });
    }
};
