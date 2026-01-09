<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

/**
 * Artisan command to display all roles, permissions, and capabilities.
 *
 * Usage:
 * - php artisan mandate:show
 * - php artisan mandate:show --guard=api
 * - php artisan mandate:show --roles
 * - php artisan mandate:show --permissions
 * - php artisan mandate:show --capabilities
 */
final class ShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mandate:show
                            {--guard= : Filter by guard}
                            {--roles : Show only roles}
                            {--permissions : Show only permissions}
                            {--capabilities : Show only capabilities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all roles, permissions, and capabilities';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string|null $guard */
        $guard = $this->option('guard');
        $showRoles = $this->option('roles');
        $showPermissions = $this->option('permissions');
        $showCapabilities = $this->option('capabilities');

        // If none specified, show all
        if (! $showRoles && ! $showPermissions && ! $showCapabilities) {
            $showRoles = true;
            $showPermissions = true;
            $showCapabilities = config('mandate.capabilities.enabled', false);
        }

        $sectionsDisplayed = 0;

        if ($showRoles) {
            $this->displayRoles($guard);
            $sectionsDisplayed++;
        }

        if ($showPermissions) {
            if ($sectionsDisplayed > 0) {
                $this->newLine();
            }
            $this->displayPermissions($guard);
            $sectionsDisplayed++;
        }

        if ($showCapabilities && config('mandate.capabilities.enabled', false)) {
            if ($sectionsDisplayed > 0) {
                $this->newLine();
            }
            $this->displayCapabilities($guard);
        }

        return self::SUCCESS;
    }

    /**
     * Display roles in a table.
     */
    private function displayRoles(?string $guard): void
    {
        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        $query = $roleClass::query()->with('permissions');

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        $roles = $query->orderBy('guard')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->components->info('No roles found.');

            return;
        }

        $this->components->info('Roles');

        $rows = $roles->map(function ($role) {
            $permissionCount = $role->permissions->count();
            $permissionNames = $role->permissions
                ->pluck('name')
                ->take(3)
                ->implode(', ');

            if ($permissionCount > 3) {
                $permissionNames .= ' ... +'.($permissionCount - 3).' more';
            }

            return [
                $role->id,
                $role->name,
                $role->guard,
                $permissionCount,
                $permissionNames ?: '-',
            ];
        });

        $this->table(
            ['ID', 'Name', 'Guard', '# Permissions', 'Permissions'],
            $rows
        );
    }

    /**
     * Display permissions in a table.
     */
    private function displayPermissions(?string $guard): void
    {
        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        $query = $permissionClass::query()->withCount('roles');

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        $permissions = $query->orderBy('guard')->orderBy('name')->get();

        if ($permissions->isEmpty()) {
            $this->components->info('No permissions found.');

            return;
        }

        $this->components->info('Permissions');

        $rows = $permissions->map(fn ($permission) => [
            $permission->id,
            $permission->name,
            $permission->guard,
            $permission->roles_count,
        ]);

        $this->table(
            ['ID', 'Name', 'Guard', '# Roles'],
            $rows
        );
    }

    /**
     * Display capabilities in a table.
     */
    private function displayCapabilities(?string $guard): void
    {
        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        $query = $capabilityClass::query()->with('permissions')->withCount('roles');

        if ($guard !== null) {
            $query->where('guard', $guard);
        }

        $capabilities = $query->orderBy('guard')->orderBy('name')->get();

        if ($capabilities->isEmpty()) {
            $this->components->info('No capabilities found.');

            return;
        }

        $this->components->info('Capabilities');

        $rows = $capabilities->map(function ($capability) {
            $permissionCount = $capability->permissions->count();
            $permissionNames = $capability->permissions
                ->pluck('name')
                ->take(3)
                ->implode(', ');

            if ($permissionCount > 3) {
                $permissionNames .= ' ... +'.($permissionCount - 3).' more';
            }

            return [
                $capability->id,
                $capability->name,
                $capability->guard,
                $capability->roles_count,
                $permissionCount,
                $permissionNames ?: '-',
            ];
        });

        $this->table(
            ['ID', 'Name', 'Guard', '# Roles', '# Permissions', 'Permissions'],
            $rows
        );
    }
}
