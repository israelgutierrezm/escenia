<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'timezone'],
            'scheduled_start_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:scheduled_start_at'],
        ];
    }
}
