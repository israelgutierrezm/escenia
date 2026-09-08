<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcasting;

use App\Domain\Broadcasting\Enums\DestinationProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateStreamDestinationRequest extends FormRequest
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
            'protocol' => ['required', Rule::in(DestinationProtocol::values())],
            'url' => ['required', 'string', 'max:2048'],
            'stream_key' => ['required', 'string', 'max:2048'],
        ];
    }
}
