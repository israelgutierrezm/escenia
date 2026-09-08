<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Tenancy\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventTemplateResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventTemplateController extends Controller
{
    public function index(TenantContext $context): AnonymousResourceCollection
    {
        // Any member who can view events may browse the templates available to
        // the tenant (its own plus system templates).
        $this->authorize('viewAny', Event::class);

        $templates = EventTemplate::query()
            ->visibleTo($context->tenantOrFail()->getKey())
            ->orderBy('name')
            ->get();

        return EventTemplateResource::collection($templates);
    }
}
