<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting an SSO login. `redirect_uri` is where the IdP returns the browser
 * (normally the SPA's callback route); the IdP enforces its registered list.
 */
class StartSsoLoginRequest extends FormRequest
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
            'redirect_uri' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
