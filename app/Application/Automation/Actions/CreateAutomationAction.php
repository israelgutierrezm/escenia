<?php

declare(strict_types=1);

namespace App\Application\Automation\Actions;

use App\Application\Automation\DTOs\CreateAutomationData;
use App\Application\Automation\DTOs\CreatedAutomation;
use App\Application\Automation\DTOs\IssuedWebhookSecret;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\Models\Automation;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\WebhookEndpoint;
use App\Domain\Automation\ValueObjects\WebhookSecret;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates an automation with its ordered steps. Step positions follow the
 * submitted order. Each step keeps only the settings its type reads; a
 * webhook's URL and secret go to the encrypted `credentials` (ADR-037).
 *
 * Every webhook step signs its deliveries (ADR-038): with the Standard
 * Webhooks secret the request brought, or one generated here. The secrets are
 * handed back once, with the new automation.
 */
final class CreateAutomationAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, CreateAutomationData $data): CreatedAutomation
    {
        return DB::transaction(function () use ($actor, $data): CreatedAutomation {
            $automation = Automation::query()->create([
                'name' => $data->name,
                'trigger' => $data->trigger,
                'is_active' => true,
                'conditions' => $data->conditions,
                'created_by' => $actor->getKey(),
            ]);

            $issued = [];

            foreach ($data->steps as $position => $step) {
                $type = AutomationStepType::from($step['type']);
                $secret = $type === AutomationStepType::Webhook ? $this->secretFor($step['config']) : null;

                $model = AutomationStep::query()->create([
                    'automation_id' => $automation->getKey(),
                    'position' => $position,
                    'type' => $type,
                    ...$this->settings($type, $step['config'], $secret),
                    'conditions' => $step['conditions'],
                ]);

                if ($secret !== null) {
                    $issued[] = new IssuedWebhookSecret($model, $secret->value);
                }
            }

            $this->audit->log('automation.created', actor: $actor, tenant: $automation->tenant, auditable: $automation, context: [
                'trigger' => $data->trigger->value,
            ]);

            return new CreatedAutomation($automation->load('steps'), $issued);
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function secretFor(array $config): WebhookSecret
    {
        $given = $config['secret'] ?? null;

        // The request only lets through Standard Webhooks secrets.
        return is_string($given) && WebhookSecret::isStandard($given) ? new WebhookSecret($given) : WebhookSecret::generate();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{config: array<string, mixed>|null, credentials: array<string, string|int|bool>|null}
     */
    private function settings(AutomationStepType $type, array $config, ?WebhookSecret $secret): array
    {
        if ($type === AutomationStepType::Webhook) {
            $url = is_string($config['url'] ?? null) ? trim($config['url']) : '';

            return ['config' => null, 'credentials' => $url === '' ? null : (new WebhookEndpoint($url, $secret))->toArray()];
        }

        return ['config' => Arr::only($config, $type->configKeys()), 'credentials' => null];
    }
}
