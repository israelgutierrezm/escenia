<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A webhook step's URL and HMAC secret are credentials (ADR-037): they move
 * from the plain `config` JSON to `credentials`, encrypted at rest (the model
 * casts it as `encrypted:array`). Reversible: down() puts them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_steps', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('config');
        });

        DB::transaction(function (): void {
            foreach (DB::table('automation_steps')->where('type', 'webhook')->lazyById() as $step) {
                $config = json_decode((string) $step->config, true);
                $config = is_array($config) ? $config : [];

                $credentials = array_filter(
                    ['url' => $config['url'] ?? null, 'secret' => $config['secret'] ?? null],
                    static fn (mixed $value): bool => is_string($value) && $value !== '',
                );
                unset($config['url'], $config['secret']);

                DB::table('automation_steps')->where('id', $step->id)->update([
                    'config' => $config === [] ? null : json_encode($config),
                    'credentials' => $credentials === [] ? null : Crypt::encryptString((string) json_encode($credentials)),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('automation_steps')->where('type', 'webhook')->whereNotNull('credentials')->lazyById() as $step) {
                $credentials = json_decode(Crypt::decryptString((string) $step->credentials), true);
                $config = json_decode((string) $step->config, true);

                // Only the shape that existed before: ADR-038's rotation fields
                // (previous secret, legacy marker) do not survive a rollback.
                DB::table('automation_steps')->where('id', $step->id)->update([
                    'config' => json_encode(array_merge(
                        is_array($config) ? $config : [],
                        is_array($credentials) ? array_intersect_key($credentials, ['url' => true, 'secret' => true]) : [],
                    )),
                ]);
            }
        });

        Schema::table('automation_steps', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });
    }
};
