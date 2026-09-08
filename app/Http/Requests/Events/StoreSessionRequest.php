<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Domain\Events\Enums\SessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(array_column(SessionStatus::cases(), 'value'))],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
