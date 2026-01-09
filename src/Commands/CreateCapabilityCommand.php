<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;

/**
 * Artisan command to create a new capability.
 *
 * Usage:
 * - php artisan mandate:capability manage-posts
 * - php artisan mandate:capability manage-posts --guard=api
 * - php artisan mandate:capability manage-posts --permissions=post:view,post:edit,post:delete
 */
final class CreateCapabilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:capability
                            {name : The capability name (e.g., manage-posts)}
                            {--guard= : The guard to create the capability for}
                            {--permissions= : Comma-separated list of permissions to include}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new capability';

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

        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var string|null $permissionsOption */
        $permissionsOption = $this->option('permissions');

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        // Check if capability already exists
        $existing = $capabilityClass::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            $this->components->warn("Capability '{$name}' already exists for guard '{$guard}'.");

            $capability = $existing;
        } else {
            $capability = $capabilityClass::create([
                'name' => $name,
                'guard' => $guard,
            ]);

            $this->components->info("Capability '{$name}' created for guard '{$guard}'.");
        }

        // Assign permissions if provided
        if ($permissionsOption !== null) {
            $permissions = array_map('trim', explode(',', $permissionsOption));
            $assignedCount = 0;

            /** @var class-string<Permission> $permissionClass */
            $permissionClass = config('mandate.models.permission', Permission::class);

            foreach ($permissions as $permissionName) {
                // Find or create the permission
                $permission = $permissionClass::findOrCreate($permissionName, $guard);

                // Check if already assigned
                if (! $capability->hasPermission($permission)) {
                    $capability->grantPermission($permission);
                    $assignedCount++;
                }
            }

            if ($assignedCount > 0) {
                $this->components->info("Assigned {$assignedCount} permission(s) to capability '{$name}'.");
            }
        }

        return self::SUCCESS;
    }
}
