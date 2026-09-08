<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Automation\Enums\AutomationStepType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One ordered step of an automation. `config` holds type-specific settings;
 * `conditions` gate whether the step runs (conditional logic).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $automation_id
 * @property int $position
 * @property AutomationStepType $type
 * @property array<string, mixed>|null $config
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
        'conditions',
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
            'conditions' => 'array',
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
