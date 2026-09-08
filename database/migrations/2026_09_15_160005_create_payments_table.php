<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment attempt against an order via a gateway. The (gateway,
 * gateway_reference) pair is unique so a replayed webhook is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('gateway_reference');
            $table->string('status')->default('pending'); // pending|succeeded|failed|refunded
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamps();

            $table->unique(['gateway', 'gateway_reference']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
