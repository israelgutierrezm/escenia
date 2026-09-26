<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the pre-generated certificate PDF lives on the certificate disk (TD-031).
 * Null until the generation job has run; the download endpoint falls back to an
 * on-demand render while it is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('pdf_path')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn('pdf_path');
        });
    }
};
