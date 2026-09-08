<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Engagement\Models\Resource;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Attaches a downloadable resource (handout, slides, link) to an event. New
 * resources go to the end of the list.
 */
final class AddResourceAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, string $title, string $url): Resource
    {
        $position = (int) Resource::query()->where('event_id', $event->getKey())->max('position');

        $resource = Resource::query()->create([
            'event_id' => $event->getKey(),
            'title' => $title,
            'url' => $url,
            'position' => $position + 1,
            'downloads_count' => 0,
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->log('engagement.resource.added', actor: $actor, tenant: $event->tenant, auditable: $resource);

        return $resource;
    }
}
