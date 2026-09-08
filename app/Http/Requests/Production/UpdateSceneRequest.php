<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSceneRequest extends FormRequest
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
        // Validate only the top-level shape: nested rules would strip keys
        // without an explicit rule from validated(), losing the definition body.
        return [
            'definition' => ['required', 'array'],
        ];
    }
}
