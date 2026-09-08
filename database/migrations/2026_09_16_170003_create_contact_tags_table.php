<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM tags on a contact (audience segmentation). Unique per (contact, tag).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('tag');
            $table->timestamps();

            $table->unique(['contact_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tags');
    }
};
