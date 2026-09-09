<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform super-admin flag (ADR-033). A super-admin is the operator who may
 * configure system-wide settings (global integration credentials, provider
 * selection). It is deliberately NOT mass-assignable and is granted out of band
 * (the `escenia:super-admin` command), never through the tenant API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
