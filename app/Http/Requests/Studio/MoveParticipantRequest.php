<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use App\Domain\Studio\Enums\ParticipantStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveParticipantRequest extends FormRequest
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
            'stage' => ['required', Rule::in(ParticipantStage::values())],
        ];
    }
}
