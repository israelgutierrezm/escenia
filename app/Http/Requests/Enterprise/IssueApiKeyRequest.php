<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Enterprise\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueApiKeyRequest extends FormRequest
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
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiKey::SCOPES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
