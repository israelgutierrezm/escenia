<?php

declare(strict_types=1);

namespace App\Http\Requests\Education;

use App\Domain\Education\Enums\AssessmentQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAssessmentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'is_published' => ['nullable', 'boolean'],
            'questions' => ['present', 'array', 'max:100'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.type' => ['required', Rule::in(AssessmentQuestionType::values())],
            'questions.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:10'],
            'questions.*.options.*.key' => ['required', 'string', 'max:32'],
            'questions.*.options.*.label' => ['required', 'string', 'max:500'],
            'questions.*.options.*.correct' => ['nullable', 'boolean'],
        ];
    }
}
