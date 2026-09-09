<?php

declare(strict_types=1);

namespace App\Domain\Education\Enums;

enum AssessmentQuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
