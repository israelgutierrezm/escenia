<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * Grants or revokes the platform super-admin flag for a user by email. This is
 * the only way the flag is set — it is never mass-assignable nor exposed to the
 * tenant API (ADR-033).
 */
class ManageSuperAdminCommand extends Command
{
    protected $signature = 'escenia:super-admin {email : The user email} {--revoke : Revoke instead of grant}';

    protected $description = 'Grant or revoke platform super-admin for a user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user found for that email.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $grant])->save();

        $this->info(($grant ? 'Granted' : 'Revoked')." super-admin for {$user->email}.");

        return self::SUCCESS;
    }
}
