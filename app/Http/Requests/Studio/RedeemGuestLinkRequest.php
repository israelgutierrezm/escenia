<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;

class RedeemGuestLinkRequest extends FormRequest
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
        ];
    }
}
