<?php

declare(strict_types=1);

use App\Domain\FeatureManagement\Contracts\FeatureFlagResolver;
use App\Domain\FeatureManagement\Models\FeatureFlag;

it('prefers the most specific scope', function () {
    [$user, $tenant] = registerTenantOwner();

    FeatureFlag::create(['key' => 'chat', 'scope' => 'global', 'enabled' => false]);
    FeatureFlag::create(['key' => 'chat', 'scope' => 'tenant', 'tenant_id' => $tenant->id, 'enabled' => true]);

    $resolver = app(FeatureFlagResolver::class);

    expect($resolver->enabled('chat', $user, null, $tenant))->toBeTrue()
        ->and($resolver->enabled('chat', $user, null, null))->toBeFalse();
});

it('returns false for an unknown flag', function () {
    expect(app(FeatureFlagResolver::class)->enabled('does-not-exist'))->toBeFalse();
});

it('honours a full percentage rollout', function () {
    FeatureFlag::create(['key' => 'beta', 'scope' => 'global', 'enabled' => true, 'rollout_percentage' => 100]);

    expect(app(FeatureFlagResolver::class)->enabled('beta'))->toBeTrue();
});
