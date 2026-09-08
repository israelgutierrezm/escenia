<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Order lifecycle: pending → paid → refunded, or pending → canceled. Guarded
 * transitions enforced with optimistic locking, like the event/broadcast/poll
 * state machines.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Canceled],
            self::Paid => [self::Refunded],
            self::Canceled, self::Refunded => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
