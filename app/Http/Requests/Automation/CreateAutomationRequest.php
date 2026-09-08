<?php

declare(strict_types=1);

namespace App\Http\Requests\Automation;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\Enums\TriggerEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The trigger and each step type are constrained; condition objects and step
 * configs are free-form maps (the engine reads them defensively and conditions
 * fail closed if malformed).
 */
class CreateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::in(TriggerEvent::values())],
            'conditions' => ['nullable', 'array'],
            'steps' => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.type' => ['required', Rule::in(AutomationStepType::values())],
            'steps.*.config' => ['nullable', 'array'],
            'steps.*.conditions' => ['nullable', 'array'],
        ];
    }
}
