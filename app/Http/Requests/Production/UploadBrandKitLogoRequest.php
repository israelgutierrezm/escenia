<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Domain\Production\Models\BrandKit;
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
        $maxPx = (int) config('branding.logo_max_px', 2000);

        return [
            'logo' => [
                'required',
                'file',
                // Checks the type sniffed from the bytes, not the client's file name.
                'mimetypes:'.implode(',', array_keys(BrandKit::LOGO_MIME_EXTENSIONS)),
                'max:'.(int) config('branding.logo_max_kb', 1024),
                'dimensions:max_width='.$maxPx.',max_height='.$maxPx,
            ],
        ];
    }
}
