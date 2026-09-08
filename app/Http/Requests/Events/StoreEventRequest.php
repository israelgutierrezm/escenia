<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Domain\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
            'workspace_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(EventType::values())],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string'],
            'timezone' => ['nullable', 'timezone'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'template_id' => ['nullable', 'string'],
        ];
    }
}
