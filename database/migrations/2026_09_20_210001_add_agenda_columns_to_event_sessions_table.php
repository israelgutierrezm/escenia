<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches event sessions for multi-session agendas: a track, a room, and an
 * optional capacity with a denormalized registration count (ADR-029).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->after('event_id')->constrained('tracks')->nullOnDelete();
            $table->string('room')->nullable()->after('title');
            $table->unsignedInteger('capacity')->nullable()->after('room');
            $table->unsignedInteger('registered_count')->default(0)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('track_id');
            $table->dropColumn(['room', 'capacity', 'registered_count']);
        });
    }
};
