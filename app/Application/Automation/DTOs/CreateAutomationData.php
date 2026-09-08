<?php

declare(strict_types=1);

namespace App\Application\Automation\DTOs;

use App\Domain\Automation\Enums\TriggerEvent;

final class CreateAutomationData
{
    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  list<array{type: string, config: array<string, mixed>, conditions: array<int, array<string, mixed>>}>  $steps
     */
    public function __construct(
        public readonly string $name,
        public readonly TriggerEvent $trigger,
        public readonly array $conditions,
        public readonly array $steps,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>> $conditions */
        $conditions = is_array($data['conditions'] ?? null) ? array_values($data['conditions']) : [];

        $steps = [];
        foreach (is_array($data['steps'] ?? null) ? $data['steps'] : [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $steps[] = [
                'type' => (string) ($step['type'] ?? ''),
                'config' => is_array($step['config'] ?? null) ? $step['config'] : [],
                'conditions' => is_array($step['conditions'] ?? null) ? array_values($step['conditions']) : [],
            ];
        }

        return new self(
            name: (string) $data['name'],
            trigger: TriggerEvent::from((string) $data['trigger']),
            conditions: $conditions,
            steps: $steps,
        );
    }
}
