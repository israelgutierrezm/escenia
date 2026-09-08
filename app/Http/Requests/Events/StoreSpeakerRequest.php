<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Domain\Events\Enums\SpeakerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpeakerRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'role' => ['nullable', Rule::in(SpeakerRole::values())],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
