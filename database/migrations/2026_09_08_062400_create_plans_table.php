<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans catalog. Entitlements are modelled as DATA (features + limits) rather
 * than as `if ($plan === 'pro')` branches scattered through the code
 * (see ADR-013). Billing itself is out of scope for Foundation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();          // 'free', 'pro', ...
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();      // list<string> of entitled feature keys
            $table->json('limits')->nullable();        // map<string, int|null> quotas
            $table->unsignedBigInteger('price')->nullable(); // minor units, never float
            $table->char('price_currency', 3)->nullable();   // ISO-4217
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
