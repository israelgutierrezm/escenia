<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Automation\ValueObjects\WebhookEndpoint;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One ordered step of an automation. `config` holds type-specific settings;
 * `credentials` the ones that are secret (a webhook's URL and signing secret),
 * encrypted at rest and hidden from serialization (ADR-037); `conditions` gate
 * whether the step runs (conditional logic).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $automation_id
 * @property int $position
 * @property AutomationStepType $type
 * @property array<string, mixed>|null $config
 * @property array<string, mixed>|null $credentials
 * @property array<int, array<string, mixed>>|null $conditions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AutomationStep extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'automation_id',
        'position',
        'type',
        'config',
        'credentials',
        'conditions',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => AutomationStepType::class,
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'conditions' => 'array',
        ];
    }

    /**
     * Where a webhook step posts to. Steps saved before ADR-037 kept it in
     * `config` (the migration moves it); it is still read there, so a step
     * written by an older instance during a rolling deploy keeps working.
     */
    public function webhookEndpoint(): ?WebhookEndpoint
    {
        if ($this->type !== AutomationStepType::Webhook) {
            return null;
        }

        return WebhookEndpoint::fromArray($this->credentials ?? $this->config ?? []);
    }

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
