<?php

declare(strict_types=1);

namespace App\Http\Requests\Networking;

use Illuminate\Foundation\Http\FormRequest;

class SendConnectionRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
