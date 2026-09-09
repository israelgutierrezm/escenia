<?php

declare(strict_types=1);

namespace App\Http\Requests\Education;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `answers` is a freeform map keyed by question ULID (value: selected option key
 * or list of keys), so it is validated only as an array; scoring is server-side.
 */
class TakeAssessmentRequest extends FormRequest
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
            'answers' => ['present', 'array'],
        ];
    }
}
