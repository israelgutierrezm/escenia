<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use App\Domain\Studio\Enums\ParticipantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmitParticipantRequest extends FormRequest
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
        ];
    }
}
