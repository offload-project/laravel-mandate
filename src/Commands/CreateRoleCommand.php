<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

/**
 * Artisan command to create a new role.
 *
 * Usage:
 * - php artisan mandate:role admin
 * - php artisan mandate:role editor --guard=api
 * - php artisan mandate:role editor --permissions=article:view,article:edit
 */
final class CreateRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:role
                            {name : The role name (e.g., admin)}
                            {--guard= : The guard to create the role for}
                            {--permissions= : Comma-separated list of permissions to assign}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        /** @var string|null $guard */
        $guard = $this->option('guard');
        $guard ??= Guard::getDefaultName();

        /** @var string|null $permissionsOption */
        $permissionsOption = $this->option('permissions');

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        // Check if role already exists
        $existing = $roleClass::query()
            ->where('name', $name)
            ->where('guard', $guard)
            ->first();

        if ($existing) {
            $this->components->warn("Role '{$name}' already exists for guard '{$guard}'.");

            $role = $existing;
        } else {
            $role = $roleClass::create([
                'name' => $name,
                'guard' => $guard,
            ]);

            $this->components->info("Role '{$name}' created for guard '{$guard}'.");
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
                if (! $role->hasPermission($permission)) {
                    $role->grantPermission($permission);
                    $assignedCount++;
                }
            }

            if ($assignedCount > 0) {
                $this->components->info("Assigned {$assignedCount} permission(s) to role '{$name}'.");
            }
        }

        return self::SUCCESS;
    }
}
