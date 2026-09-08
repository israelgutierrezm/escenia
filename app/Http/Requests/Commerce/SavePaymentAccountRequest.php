<?php

declare(strict_types=1);

namespace App\Http\Requests\Commerce;

use App\Domain\Commerce\Enums\PaymentGatewayName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentAccountRequest extends FormRequest
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
            'gateway' => ['required', Rule::in(PaymentGatewayName::values())],
            'display_name' => ['required', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'webhook_secret' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
