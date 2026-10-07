<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\FirstPartyUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Signing out. `return_to` is where to land once the IdP session is also over
 * (single logout, ADR-036) — a first-party app page, typically its login.
 */
class LogoutRequest extends FormRequest
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
            'return_to' => ['nullable', 'string', 'max:2048', 'url:http,https', new FirstPartyUrl],
        ];
    }
}
