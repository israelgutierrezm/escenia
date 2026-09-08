<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a pair of columns (`{key}` minor units + `{key}_currency`) to a
 * {@see Money} value object. Usage: `'price' => MoneyCast::class` expects a
 * `price` integer column and a `price_currency` char(3) column.
 *
 * @implements CastsAttributes<Money|null, Money|null>
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $currency = $attributes["{$key}_currency"] ?? null;

        if ($value === null || $currency === null) {
            return null;
        }

        return new Money((int) $value, (string) $currency);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null, "{$key}_currency" => null];
        }

        // The cast's typed contract guarantees a Money instance here.
        return [
            $key => $value->minorUnits,
            "{$key}_currency" => $value->currency,
        ];
    }
}
