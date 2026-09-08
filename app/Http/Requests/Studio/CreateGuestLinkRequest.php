<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use App\Domain\Studio\Enums\ParticipantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGuestLinkRequest extends FormRequest
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
            'role' => ['required', Rule::in(ParticipantRole::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'single_use' => ['nullable', 'boolean'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
