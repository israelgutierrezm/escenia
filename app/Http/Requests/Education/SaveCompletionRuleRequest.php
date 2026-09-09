<?php

declare(strict_types=1);

namespace App\Http\Requests\Education;

use Illuminate\Foundation\Http\FormRequest;

class SaveCompletionRuleRequest extends FormRequest
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
            'min_watch_seconds' => ['nullable', 'integer', 'min:0'],
            'require_assessment' => ['nullable', 'boolean'],
        ];
    }
}
