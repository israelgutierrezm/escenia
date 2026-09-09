<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomDomainRequest extends FormRequest
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
            'hostname' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/',
            ],
            'workspace_id' => ['nullable', 'string'],
        ];
    }
}
