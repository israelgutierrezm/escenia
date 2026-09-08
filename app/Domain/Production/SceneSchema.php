<?php

declare(strict_types=1);

namespace App\Domain\Production;

/**
 * Versioning of the scene definition document (ADR-006). Every stored scene
 * version carries a schema_version; incompatible changes bump CURRENT_VERSION
 * and add an upgrade step to the SceneDefinitionMigrator.
 */
final class SceneSchema
{
    public const CURRENT_VERSION = '1.0';

    /**
     * A blank scene definition at the current schema version.
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'schema_version' => self::CURRENT_VERSION,
            'layout' => 'single',
            'background' => null,
            'elements' => [],
        ];
    }
}
