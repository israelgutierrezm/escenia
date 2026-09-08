<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    /**
     * List the tenants the authenticated user is a member of. This is the entry
     * point the SPA uses to pick a tenant before sending X-Tenant-Id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = $request->user()
            ->tenants()
            ->orderBy('name')
            ->get();

        return TenantResource::collection($tenants);
    }
}
