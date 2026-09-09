<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise residency settings on the tenant (additive). `data_region` records
 * where the tenant's data should live; `is_dedicated` flags tenants that run on
 * dedicated infrastructure. Enforcement (actual region pinning / dedicated
 * provisioning) is future work — these columns make the intent explicit and
 * auditable today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('data_region', 16)->default('us')->after('status');
            $table->boolean('is_dedicated')->default(false)->after('data_region');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['data_region', 'is_dedicated']);
        });
    }
};
