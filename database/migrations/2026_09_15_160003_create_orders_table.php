<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase order for tickets to an event. Guarded lifecycle
 * (pending → paid → refunded, or pending → canceled). Totals are integer minor
 * units in the order's currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('status')->default('pending'); // pending|paid|canceled|refunded
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('gateway');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
