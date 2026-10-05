<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $workspace_id
 * @property string $name
 * @property array<string, mixed>|null $tokens
 * @property string|null $logo_path
 * @property string|null $logo_mime
 * @property bool $is_default
 */
class BrandKit extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'name',
        'tokens',
        'logo_path',
        'logo_mime',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The stored logo as a data URI (read back from the branding disk), or null.
     * Keeps the image offline-embeddable (certificate PDF, admin preview) without
     * a public URL.
     */
    public function logoDataUri(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        $disk = Storage::disk((string) config('branding.logo_disk'));

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $this->logo_mime ?? 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($this->logo_path));
    }
}
