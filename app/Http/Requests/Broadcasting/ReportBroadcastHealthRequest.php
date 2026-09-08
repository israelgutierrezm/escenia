<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcasting;

use App\Domain\Broadcasting\Enums\BroadcastHealth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportBroadcastHealthRequest extends FormRequest
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
            'health' => ['required', Rule::in(BroadcastHealth::values())],
        ];
    }
}
