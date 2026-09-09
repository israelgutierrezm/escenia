<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\AccessControl\Services\AccessGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'is_system' => AccessGuard::isSystemRole((string) $this->name),
            'permissions' => $this->permissions->pluck('name')->values()->all(),
        ];
    }
}
