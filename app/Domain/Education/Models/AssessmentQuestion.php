<?php

declare(strict_types=1);

namespace App\Domain\Education\Models;

use App\Domain\Education\Enums\AssessmentQuestionType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A question on an assessment. `options` is a list of
 * `{key, label, correct}`; the `correct` flag is used only for server-side
 * scoring and is never serialized to attendees.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $assessment_id
 * @property int $position
 * @property string $prompt
 * @property AssessmentQuestionType $type
 * @property int $points
 * @property array<int, array<string, mixed>> $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssessmentQuestion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'assessment_id',
        'position',
        'prompt',
        'type',
        'points',
        'options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => AssessmentQuestionType::class,
            'points' => 'integer',
            'options' => 'array',
        ];
    }

    /**
     * The set of correct option keys for this question.
     *
     * @return list<string>
     */
    public function correctKeys(): array
    {
        $keys = [];

        foreach ($this->options as $option) {
            if (($option['correct'] ?? false) === true) {
                $keys[] = (string) ($option['key'] ?? '');
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
