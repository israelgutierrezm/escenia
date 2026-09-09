<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSsoConnectionRequest extends FormRequest
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
            'display_name' => ['sometimes', 'string', 'max:255'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config' => ['sometimes', 'array'],
            'default_role' => ['sometimes', 'string', Rule::in([TenantRole::Admin->value, TenantRole::Member->value])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
