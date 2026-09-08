<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket type sold for an event. Money is stored as integer minor units plus
 * an ISO-4217 currency (never a float). `compare_at_minor` is the optional
 * strike-through price used to surface an offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->unsignedBigInteger('compare_at_minor')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = unlimited
            $table->unsignedInteger('sold_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
