<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The SSO callback payload. `code` is the provider's authorization code; the
 * remaining fields are optional hints the fake/OIDC adapters may use. The
 * adapter is responsible for verifying the payload before any identity is
 * trusted.
 */
class SsoCallbackRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['nullable', 'string', 'max:2048'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
