<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

/**
 * The value type of a setting, used to validate input and to render the right
 * control in the admin UI. `Secret` values are write-only over the API (masked
 * on read).
 */
enum SettingType: string
{
    case String = 'string';
    case Bool = 'bool';
    case Int = 'int';
    case Select = 'select';
    case Secret = 'secret';
    case Json = 'json';

    public function isSecret(): bool
    {
        return $this === self::Secret;
    }
}
