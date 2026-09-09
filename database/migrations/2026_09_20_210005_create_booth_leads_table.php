<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lead captured when an attendee visits a booth (expresses interest). Unique
 * per (booth, attendee) so a booth's lead count stays honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booth_leads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booth_id')->constrained('booths')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('attendees')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['booth_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booth_leads');
    }
};
