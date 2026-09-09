<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A batch of setting changes: each entry names a catalog key and its new value.
 * Type/scope validation happens in the action against the catalog (the value may
 * be a string, bool, int, or object depending on the setting).
 */
class UpdateSettingsRequest extends FormRequest
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
            'settings' => ['present', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['present'],
        ];
    }
}
