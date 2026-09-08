<?php

declare(strict_types=1);

use App\Domain\Production\SceneSchema;
use App\Infrastructure\Production\DefaultSceneDefinitionMigrator;

it('produces a blank definition at the current schema version', function () {
    $blank = SceneSchema::blank();

    expect($blank['schema_version'])->toBe(SceneSchema::CURRENT_VERSION)
        ->and($blank['elements'])->toBe([]);
});

it('normalizes a definition to the current schema version while keeping data', function () {
    $migrator = new DefaultSceneDefinitionMigrator;

    $migrated = $migrator->migrate(['schema_version' => '0.9', 'layout' => 'split']);

    expect($migrated['schema_version'])->toBe(SceneSchema::CURRENT_VERSION)
        ->and($migrated['layout'])->toBe('split');
});
