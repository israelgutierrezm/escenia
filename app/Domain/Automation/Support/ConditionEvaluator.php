<?php

declare(strict_types=1);

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Enums\ConditionOperator;

/**
 * Evaluates automation conditions against a flattened context. Conditions are
 * pure data (`{field, op, value}`) combined with AND — there is never any code
 * execution. Malformed conditions fail closed (the automation does not fire).
 */
final class ConditionEvaluator
{
    /**
     * @param  array<int, mixed>|null  $conditions  raw condition objects (from JSON)
     * @param  array<string, mixed>  $context
     */
    public function passes(?array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                return false;
            }

            $field = (string) ($condition['field'] ?? '');
            $operator = ConditionOperator::tryFrom((string) ($condition['op'] ?? ''));

            if ($field === '' || $operator === null) {
                return false;
            }

            if (! $operator->evaluate($context[$field] ?? null, $condition['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
