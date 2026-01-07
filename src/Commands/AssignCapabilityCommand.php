<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Role;

/**
 * Artisan command to assign a capability to a role.
 *
 * Usage:
 * - php artisan mandate:assign-capability admin manage-posts
 * - php artisan mandate:assign-capability admin manage-posts --guard=api
 */
final class AssignCapabilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:assign-capability
                            {role : The role name to assign the capability to}
                            {capability : The capability name to assign}
                            {--guard= : The guard to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a capability to a role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('mandate.capabilities.enabled', false)) {
            $this->components->error(
                'Capabilities feature is not enabled. '
                .'Set mandate.capabilities.enabled to true in your configuration.'
            );

            return self::FAILURE;
        }

        /** @var string $roleName */
        $roleName = $this->argument('role');

        /** @var string $capabilityName */
        $capabilityName = $this->argument('capability');

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        // Find the role
        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        $role = $roleClass::query()
            ->where('name', $roleName)
            ->where('guard', $guard)
            ->first();

        if ($role === null) {
            $this->components->error(
                "Role '{$roleName}' not found for guard '{$guard}'. "
                .'Create it first with: php artisan mandate:role '.$roleName
            );

            return self::FAILURE;
        }

        // Find the capability
        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        $capability = $capabilityClass::query()
            ->where('name', $capabilityName)
            ->where('guard', $guard)
            ->first();

        if ($capability === null) {
            $this->components->error(
                "Capability '{$capabilityName}' not found for guard '{$guard}'. "
                .'Create it first with: php artisan mandate:capability '.$capabilityName
            );

            return self::FAILURE;
        }

        // Check if role already has the capability
        if ($role->hasCapability($capability)) {
            $this->components->warn(
                "Role '{$roleName}' already has capability '{$capabilityName}'."
            );

            return self::SUCCESS;
        }

        // Assign the capability
        $role->assignCapability($capability);

        $this->components->info(
            "Capability '{$capabilityName}' assigned to role '{$roleName}'."
        );

        return self::SUCCESS;
    }
}
