<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Enterprise\Enums\DataRegion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
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
            'data_region' => ['sometimes', 'string', Rule::in(DataRegion::values())],
            'is_dedicated' => ['sometimes', 'boolean'],
        ];
    }
}
