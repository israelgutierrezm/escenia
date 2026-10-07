<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified SSO domains are re-checked daily (ADR-035): when the last check ran
 * and since when it has been failing (a grace period before revocation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->timestamp('domain_checked_at')->nullable()->after('domain_verified_at');
            $table->timestamp('domain_check_failed_at')->nullable()->after('domain_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('sso_connections', function (Blueprint $table): void {
            $table->dropColumn(['domain_checked_at', 'domain_check_failed_at']);
        });
    }
};
