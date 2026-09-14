<?php

declare(strict_types=1);

namespace App\Http\Requests\Networking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposeMeetingRequest extends FormRequest
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
            'attendee_id' => ['required', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', Rule::in([15, 30, 45, 60])],
            'topic' => ['nullable', 'string', 'max:300'],
        ];
    }
}
