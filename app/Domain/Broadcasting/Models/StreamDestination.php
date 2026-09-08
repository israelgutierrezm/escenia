<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Models;

use App\Domain\Broadcasting\Enums\DestinationProtocol;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $workspace_id
 * @property string $name
 * @property DestinationProtocol $protocol
 * @property string $url
 * @property string $stream_key
 * @property bool $is_enabled
 */
class StreamDestination extends Model
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
        'protocol',
        'url',
        'stream_key',
        'is_enabled',
    ];

    /**
     * The stream key is a secret; never serialize it.
     *
     * @var list<string>
     */
    protected $hidden = [
        'stream_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'protocol' => DestinationProtocol::class,
            'stream_key' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
