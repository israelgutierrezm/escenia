<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl;

use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeMembershipRoleRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(TenantRole::values())],
        ];
    }
}
