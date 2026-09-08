<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use Illuminate\Support\Str;

/**
 * Public identity strategy (ADR-004): every control-plane entity keeps a
 * compact auto-increment BIGINT primary key for internal joins/foreign keys,
 * and exposes an opaque, non-sequential ULID as its public identifier.
 *
 * The BIGINT key is NEVER exposed: it is hidden from array/JSON serialization
 * and route-model-binding resolves on the public ULID column instead.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            $column = $model->getPublicIdColumn();

            if (blank($model->{$column})) {
                $model->{$column} = strtolower((string) Str::ulid());
            }
        });
    }

    public function initializeHasPublicId(): void
    {
        // Defense in depth: even if a Resource is bypassed, the internal
        // primary key must never leak through toArray()/toJson().
        $this->hidden = array_values(array_unique(
            array_merge($this->getHidden(), [$this->getKeyName()])
        ));
    }

    public function getPublicIdColumn(): string
    {
        return 'ulid';
    }

    public function getRouteKeyName(): string
    {
        return $this->getPublicIdColumn();
    }
}
