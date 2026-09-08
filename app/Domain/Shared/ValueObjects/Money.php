<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable monetary amount stored as integer minor units (e.g. cents) plus an
 * ISO-4217 currency code. Money is NEVER represented as a float anywhere in the
 * system (see CLAUDE.md prohibitions). Commerce is out of scope for Foundation,
 * but this primitive exists from day one so the rule cannot drift.
 */
final class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public readonly int $minorUnits,
        public readonly string $currency,
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO-4217 currency code: [{$currency}].");
        }
    }

    public static function of(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    /**
     * @return array{minor_units: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'minor_units' => $this->minorUnits,
            'currency' => $this->currency,
        ];
    }

    /**
     * @return array{minor_units: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return number_format($this->minorUnits / 100, 2, '.', '').' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: [{$this->currency}] vs [{$other->currency}]."
            );
        }
    }
}
