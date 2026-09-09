<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise;

use App\Domain\Sponsorship\Enums\SponsorTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSponsorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', Rule::in(SponsorTier::values())],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
