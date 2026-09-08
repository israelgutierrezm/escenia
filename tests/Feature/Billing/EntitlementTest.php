<?php

declare(strict_types=1);

use App\Domain\Billing\Contracts\EntitlementResolver;

it('resolves features and limits from the tenant plan', function () {
    [, $tenant] = registerTenantOwner(); // defaults to the "free" plan

    $resolver = app(EntitlementResolver::class);

    expect($resolver->allows($tenant, 'workspaces'))->toBeTrue()
        ->and($resolver->allows($tenant, 'white_label'))->toBeFalse()
        ->and($resolver->limit($tenant, 'max_workspaces'))->toBe(3);
});
