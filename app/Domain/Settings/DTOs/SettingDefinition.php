<?php

declare(strict_types=1);

namespace App\Domain\Settings\DTOs;

use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Enums\SettingType;

/**
 * A catalog entry describing one configurable setting: what it is, where it may
 * be set, its type, and where its default comes from (an existing config/env
 * key). The catalog being data — not free-form keys — is what makes "everything
 * configurable" safe and bounded.
 */
final class SettingDefinition
{
    /**
     * @param  list<string>  $options  allowed values for a `select` type
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly SettingScope $scope,
        public readonly SettingType $type,
        public readonly string $configKey,
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly array $options = [],
    ) {}

    public function isSecret(): bool
    {
        return $this->type->isSecret();
    }

    /**
     * The default value: the current config/env value this setting overrides.
     */
    public function default(): mixed
    {
        return config($this->configKey);
    }
}
