<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per CTA click by an attendee (clicks may repeat; the denormalized
 * counter lives on the CTA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cta_clicks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cta_id')->constrained('ctas')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->nullOnDelete();
            $table->timestamps();

            $table->index('cta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cta_clicks');
    }
};
