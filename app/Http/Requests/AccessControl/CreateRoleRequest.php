<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl;

use App\Domain\AccessControl\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRoleRequest extends FormRequest
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
            // URL-safe role name (no spaces): used as the route key.
            'name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_-]{1,63}$/'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }
}
