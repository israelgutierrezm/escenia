<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extraction bookkeeping for the analytics stream (ADR-031). `exported_at` is a
 * write-once high-water marker set when a row has been shipped to the warehouse
 * (like the Outbox `processed_at`); the telemetry facts themselves stay
 * immutable. The index supports the "unexported, ordered by id" scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->timestamp('exported_at')->nullable()->after('created_at');
            $table->index(['exported_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['exported_at', 'id']);
            $table->dropColumn('exported_at');
        });
    }
};
