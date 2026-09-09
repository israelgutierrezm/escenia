<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;

class SyncMemberRolesRequest extends FormRequest
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
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'max:64'],
        ];
    }
}
