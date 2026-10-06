<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomDomainRequest extends FormRequest
{
    /**
     * A bare DNS hostname (labels of 1–63 chars, no edge hyphens, at least one
     * dot): no scheme, path, port or `@`. Shared with SSO email domains.
     */
    public const HOSTNAME_RULE = 'regex:/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/';

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
                self::HOSTNAME_RULE,
            ],
            'workspace_id' => ['nullable', 'string'],
        ];
    }
}
