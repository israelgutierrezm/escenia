<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;
use App\Domain\Registration\Models\ContactTag;

/**
 * CRM action: tags the trigger's contact. Idempotent (unique per contact+tag).
 */
final class TagContactStepHandler implements StepHandler
{
    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $tag = trim((string) ($step->config['tag'] ?? ''));
        $contactId = (int) ($context['_contact_id'] ?? 0);

        if ($tag === '' || $contactId === 0) {
            return StepOutcome::done('tag_contact skipped');
        }

        ContactTag::query()->firstOrCreate(['contact_id' => $contactId, 'tag' => $tag]);

        return StepOutcome::done("tagged: {$tag}");
    }
}
