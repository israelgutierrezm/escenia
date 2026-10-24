<?php

declare(strict_types=1);

namespace App\Http\Requests\Automation;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\Enums\ConditionOperator;
use App\Domain\Automation\Enums\TriggerEvent;
use App\Domain\Automation\ValueObjects\WebhookSecret;
use App\Infrastructure\Http\OutboundUrlGuard;
use App\Rules\PublicOutboundUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The trigger, the conditions and each step's settings are validated by type,
 * so a broken automation is refused when saved rather than discovered when it
 * runs. A webhook must be an https URL whose host resolves to a public address
 * (the egress guard checks again when it is called, ADR-037).
 */
class CreateAutomationRequest extends FormRequest
{
    /** The longest wait step: a year. */
    private const MAX_WAIT_SECONDS = 31_536_000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(OutboundUrlGuard $guard): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::in(TriggerEvent::values())],
            ...$this->conditionRules('conditions'),
            'steps' => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.type' => ['required', Rule::in(AutomationStepType::values())],
            'steps.*.config' => ['nullable', 'array'],
            ...$this->conditionRules('steps.*.conditions'),

            // Each step type's settings (what its handler reads).
            'steps.*.config.url' => ['exclude_unless:steps.*.type,webhook', 'bail', 'required', 'string', 'max:2048', 'url:https', new PublicOutboundUrl($guard)],
            // Optional: left empty, Escenia generates one (ADR-038).
            'steps.*.config.secret' => ['exclude_unless:steps.*.type,webhook', 'bail', 'nullable', 'string', 'max:255', static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! WebhookSecret::isStandard($value)) {
                    $fail('The :attribute must be a Standard Webhooks secret (whsec_ + base64 of 24 to 64 random bytes), or be left empty so one is generated.');
                }
            }],
            'steps.*.config.tag' => ['exclude_unless:steps.*.type,tag_contact', 'required', 'string', 'max:255'],
            'steps.*.config.subject' => ['exclude_unless:steps.*.type,notify', 'required', 'string', 'max:255'],
            'steps.*.config.body' => ['exclude_unless:steps.*.type,notify', 'required', 'string', 'max:5000'],
            'steps.*.config.seconds' => ['exclude_unless:steps.*.type,wait', 'required', 'integer', 'min:1', 'max:'.self::MAX_WAIT_SECONDS],
        ];
    }

    /**
     * Conditions are {field, op, value} objects; the engine would fail closed on
     * a malformed one, so it is refused here instead.
     *
     * @return array<string, array<int, mixed>>
     */
    private function conditionRules(string $prefix): array
    {
        return [
            $prefix => ['nullable', 'array', 'max:20'],
            "{$prefix}.*" => ['array'],
            "{$prefix}.*.field" => ['required', 'string', 'max:100'],
            "{$prefix}.*.op" => ['required', Rule::enum(ConditionOperator::class)],
            "{$prefix}.*.value" => ['present'],
        ];
    }
}
