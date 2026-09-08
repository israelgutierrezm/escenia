<?php

declare(strict_types=1);

namespace App\Http\Requests\Automation;

use Illuminate\Foundation\Http\FormRequest;

class SetAutomationActiveRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
