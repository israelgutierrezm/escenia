<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public registration submission. `answers` is a freeform map keyed by the
 * form's field keys, so it is validated only as an array (its shape is defined
 * by the tenant's form, not by this endpoint).
 */
class RegisterAttendeeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'answers' => ['nullable', 'array'],
            'attribution' => ['nullable', 'array'],
            'attribution.utm_source' => ['nullable', 'string', 'max:255'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:255'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:255'],
            'attribution.utm_term' => ['nullable', 'string', 'max:255'],
            'attribution.utm_content' => ['nullable', 'string', 'max:255'],
            'attribution.referrer' => ['nullable', 'string', 'max:2048'],
            'attribution.landing_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
