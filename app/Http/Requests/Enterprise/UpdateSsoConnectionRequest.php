<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Tenancy\Enums\TenantRole;
use App\Http\Requests\Enterprise\Concerns\ValidatesSsoConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSsoConnectionRequest extends FormRequest
{
    use ValidatesSsoConfig;

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
            'display_name' => ['sometimes', 'string', 'max:255'],
            'domain' => ['sometimes', 'string', 'max:255', CreateCustomDomainRequest::HOSTNAME_RULE],
            'config' => ['sometimes', 'array'],
            'default_role' => ['sometimes', 'string', Rule::in([TenantRole::Admin->value, TenantRole::Member->value])],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->ssoConfigRules(),
        ];
    }
}
