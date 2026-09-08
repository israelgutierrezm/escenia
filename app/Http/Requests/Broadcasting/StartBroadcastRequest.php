<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class StartBroadcastRequest extends FormRequest
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
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['required', 'string'],
            'record' => ['nullable', 'boolean'],
        ];
    }
}
