<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Rules\FirstPartyUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting an SSO login. `redirect_uri` is the SPA route the browser comes back
 * to — the IdP's redirect target for OIDC, the post-login landing for SAML —
 * and must belong to a first-party app.
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
            'redirect_uri' => ['nullable', 'string', 'max:2048', 'url:http,https', new FirstPartyUrl],
        ];
    }
}
