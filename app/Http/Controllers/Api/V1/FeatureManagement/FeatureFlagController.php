<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FeatureManagement;

use App\Domain\FeatureManagement\Contracts\FeatureFlagResolver;
use App\Domain\FeatureManagement\Models\FeatureFlag;
use App\Domain\Tenancy\Context\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    /**
     * Resolve every known flag key for the current user + tenant. The SPA reads
     * this once to configure which capabilities are visible.
     */
    public function __invoke(Request $request, FeatureFlagResolver $resolver, TenantContext $context): JsonResponse
    {
        $keys = FeatureFlag::query()->distinct()->pluck('key');

        $resolved = $keys->mapWithKeys(fn (string $key): array => [
            $key => $resolver->enabled($key, $request->user(), null, $context->tenant()),
        ]);

        return response()->json(['data' => $resolved]);
    }
}
