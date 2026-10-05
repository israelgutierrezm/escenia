<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandKitLogoRequest extends FormRequest
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
        /** @var list<string> $mimes */
        $mimes = (array) config('branding.logo_mimes', ['png', 'jpg', 'jpeg']);

        return [
            'logo' => [
                'required',
                'file',
                'mimes:'.implode(',', $mimes),
                'max:'.(int) config('branding.logo_max_kb', 1024),
            ],
        ];
    }
}
