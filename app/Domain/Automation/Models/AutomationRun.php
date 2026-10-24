<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\AutomationRunStatus;
use App\Domain\Automation\Enums\TriggerEvent;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single execution of an automation for one trigger occurrence. `context` is
 * the snapshot conditions and steps read from.
 *
 * `resume_at` has two meanings (ADR-038): while `waiting` it is when the run
 * continues (after a wait step, or the next attempt of a failed step, counted
 * by `step_attempts`); while `running` it is the lease of the worker executing
 * it — the worker only writes while its lease is unchanged, and a run whose
 * lease expired (the worker died) is picked up again from its last checkpoint.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $automation_id
 * @property int|null $outbox_event_id
 * @property TriggerEvent $trigger
 * @property array<string, mixed>|null $context
 * @property AutomationRunStatus $status
 * @property int $current_position
 * @property int $step_attempts
 * @property Carbon|null $resume_at
 * @property array<int, array<string, mixed>>|null $log
 * @property string|null $failed_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AutomationRun extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /** How long a worker holds a run; renewed at every checkpoint. */
    public const LEASE_SECONDS = 600;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'automation_id',
        'outbox_event_id',
        'trigger',
        'context',
        'status',
        'current_position',
        'step_attempts',
        'resume_at',
        'log',
        'failed_reason',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => TriggerEvent::class,
            'context' => 'array',
            'status' => AutomationRunStatus::class,
            'current_position' => 'integer',
            'step_attempts' => 'integer',
            'resume_at' => 'datetime',
            'log' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
