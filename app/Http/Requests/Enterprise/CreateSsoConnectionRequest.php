<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSsoConnectionRequest extends FormRequest
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
            'provider' => ['required', 'string', Rule::in(SsoProvider::values())],
            'display_name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            // SSO may only ever provision admin/member — never owner. A tenant's
            // ownership is not delegated to an external IdP (privilege-escalation guard).
            'default_role' => ['nullable', 'string', Rule::in([TenantRole::Admin->value, TenantRole::Member->value])],
        ];
    }
}
