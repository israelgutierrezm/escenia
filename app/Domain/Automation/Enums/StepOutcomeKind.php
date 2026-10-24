<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * What a step reported: it finished (`done`), failed for good but the sequence
 * goes on (`failed`), parks the run until later (`wait`), or failed for a
 * transient reason and should be attempted again (`retry`, ADR-038).
 */
enum StepOutcomeKind: string
{
    case Done = 'done';
    case Failed = 'failed';
    case Wait = 'wait';
    case Retry = 'retry';
}
