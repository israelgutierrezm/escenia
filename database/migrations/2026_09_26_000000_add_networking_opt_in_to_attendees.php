<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Networking is opt-in (privacy): an attendee only appears in the people
 * directory — and can only be sent connection/meeting requests — once they
 * enable it. Defaults to off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->boolean('networking_opt_in')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropColumn('networking_opt_in');
        });
    }
};
