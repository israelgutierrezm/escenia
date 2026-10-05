<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A brand kit can carry an uploaded logo (stored on the branding disk). Used on
 * the certificate (embedded as a data URI) and shown in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('tokens');
            $table->string('logo_mime')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'logo_mime']);
        });
    }
};
