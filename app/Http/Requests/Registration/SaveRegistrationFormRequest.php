<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use App\Domain\Registration\Enums\FieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Host defines the registration form: whether it is open and the ordered custom
 * fields (beyond the always-present name + email). Field structure is fully
 * constrained here, so `validated('fields')` returns a clean, typed shape.
 */
class SaveRegistrationFormRequest extends FormRequest
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
            'is_open' => ['required', 'boolean'],
            'fields' => ['present', 'array'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(FieldType::values())],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['required', 'string', 'max:255'],
        ];
    }
}
