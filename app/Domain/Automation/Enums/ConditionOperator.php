<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * Operators for automation conditions. Conditions are pure data ({field, op,
 * value}) evaluated against a flattened context map — there is never any code
 * execution.
 */
enum ConditionOperator: string
{
    case Eq = 'eq';
    case Ne = 'ne';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Contains = 'contains';

    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return match ($this) {
            self::Eq => $this->normalize($actual) === $this->normalize($expected),
            self::Ne => $this->normalize($actual) !== $this->normalize($expected),
            self::Gt => $this->numeric($actual) > $this->numeric($expected),
            self::Gte => $this->numeric($actual) >= $this->numeric($expected),
            self::Lt => $this->numeric($actual) < $this->numeric($expected),
            self::Lte => $this->numeric($actual) <= $this->numeric($expected),
            self::Contains => str_contains(
                strtolower((string) (is_scalar($actual) ? $actual : '')),
                strtolower((string) (is_scalar($expected) ? $expected : '')),
            ),
        };
    }

    /**
     * Compare scalars as lowercased strings so "Webinar" == "webinar" and
     * numbers compare regardless of int/string origin.
     */
    private function normalize(mixed $value): string
    {
        return strtolower((string) (is_scalar($value) ? $value : ''));
    }

    private function numeric(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $o): string => $o->value, self::cases());
    }
}
