<?php

declare(strict_types=1);

namespace App\Application\Broadcasting\Actions;

use App\Application\Broadcasting\DTOs\CreateStreamDestinationData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Broadcasting\Models\StreamDestination;
use App\Domain\Identity\Models\User;
use App\Domain\Workspaces\Models\Workspace;

/**
 * Creates a streaming destination. The stream key is encrypted at rest by the
 * model cast and is never written to the audit trail.
 */
final class CreateStreamDestinationAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Workspace $workspace, User $actor, CreateStreamDestinationData $data): StreamDestination
    {
        $destination = StreamDestination::create([
            'tenant_id' => $workspace->tenant_id,
            'workspace_id' => $workspace->getKey(),
            'name' => $data->name,
            'protocol' => $data->protocol,
            'url' => $data->url,
            'stream_key' => $data->streamKey,
            'is_enabled' => true,
        ]);

        // Never log the stream key.
        $this->audit->log('stream_destination.created', actor: $actor, tenant: $workspace->tenant, auditable: $destination, context: [
            'name' => $data->name,
            'protocol' => $data->protocol->value,
        ]);

        return $destination;
    }
}
