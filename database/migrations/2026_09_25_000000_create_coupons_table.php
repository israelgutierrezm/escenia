<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount coupons (TD-022): a code redeemed at checkout to reduce the order
 * total. Scoped per event; money stays in integer minor units. Orders gain a
 * discount and an optional link to the coupon used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('code');
            $table->string('discount_type'); // percent | fixed
            $table->unsignedInteger('discount_value'); // 1-100 for percent, minor units for fixed
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'code'], 'coupon_event_code_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('discount_minor')->default(0)->after('total_minor');
            $table->foreignId('coupon_id')->nullable()->after('discount_minor')->constrained('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_minor');
        });
        Schema::dropIfExists('coupons');
    }
};
