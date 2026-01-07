<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

/**
 * Artisan command to display all roles and permissions.
 *
 * Usage:
 * - php artisan mandate:show
 * - php artisan mandate:show --guard=api
 * - php artisan mandate:show --roles
 * - php artisan mandate:show --permissions
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
                            {--permissions : Show only permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all roles and permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string|null $guard */
        $guard = $this->option('guard');
        $showRoles = $this->option('roles');
        $showPermissions = $this->option('permissions');

        // If neither specified, show both
        if (! $showRoles && ! $showPermissions) {
            $showRoles = true;
            $showPermissions = true;
        }

        if ($showRoles) {
            $this->displayRoles($guard);
        }

        if ($showPermissions) {
            if ($showRoles) {
                $this->newLine();
            }
            $this->displayPermissions($guard);
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
}
