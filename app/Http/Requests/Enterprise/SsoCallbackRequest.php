<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The SSO callback payload. `code` is the provider's authorization code and
 * `state` the value the start step issued — it must be presented from the same
 * browser (binding cookie) and only once. The redirect URI, nonce and PKCE
 * verifier come from the server-side attempt, never from this body. `email` /
 * `name` are hints only the dev/test fake reads; real adapters ignore them.
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
            'state' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
