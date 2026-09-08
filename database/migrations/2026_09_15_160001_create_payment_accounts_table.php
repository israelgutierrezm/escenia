<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's connected payment gateway. Credentials and the webhook secret are
 * encrypted at rest and never serialized or logged (like stream keys).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('gateway'); // fake|stripe|mercadopago
            $table->string('display_name');
            $table->text('credentials'); // encrypted JSON (api keys/secrets)
            $table->text('webhook_secret')->nullable(); // encrypted
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
