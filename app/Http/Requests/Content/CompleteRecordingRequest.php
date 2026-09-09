<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class CompleteRecordingRequest extends FormRequest
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
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'format' => ['nullable', 'string', 'max:32'],
        ];
    }
}
