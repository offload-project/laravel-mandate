<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Upgrade from Spatie Laravel Permission to Mandate.
 *
 * This command helps migrate data from Spatie's tables to Mandate's tables,
 * and optionally converts PermissionsSets to Capabilities.
 */
#[AsCommand(name: 'mandate:upgrade-from-spatie')]
final class UpgradeFromSpatieCommand extends Command
{
    protected $signature = 'mandate:upgrade-from-spatie
                            {--dry-run : Show what would be migrated without making changes}
                            {--skip-permissions : Skip migrating permissions}
                            {--skip-roles : Skip migrating roles}
                            {--skip-assignments : Skip migrating role/permission assignments}
                            {--create-capabilities : Create capabilities from permission prefixes}
                            {--convert-permission-sets : Convert #[PermissionsSet] classes to capabilities}
                            {--permission-sets-path= : Path to scan for PermissionsSet classes}';

    protected $description = 'Migrate data from Spatie Laravel Permission to Mandate';

    private bool $dryRun = false;

    private int $permissionsCreated = 0;

    private int $rolesCreated = 0;

    private int $capabilitiesCreated = 0;

    private int $permissionSetsConverted = 0;

    private int $rolePermissionsAssigned = 0;

    private int $userRolesAssigned = 0;

    private int $userPermissionsAssigned = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->warn('Running in dry-run mode. No changes will be made.');
            $this->newLine();
        }

        // Check if Spatie tables exist
        if (! $this->spatieTablesExist()) {
            $this->components->error('Spatie Laravel Permission tables not found.');
            $this->components->info('Expected tables: permissions, roles, model_has_permissions, model_has_roles, role_has_permissions');

            return self::FAILURE;
        }

        // Check if Mandate tables exist
        if (! $this->mandateTablesExist()) {
            $this->components->error('Mandate tables not found. Please run migrations first:');
            $this->components->info('php artisan vendor:publish --tag=mandate-migrations');
            $this->components->info('php artisan migrate');

            return self::FAILURE;
        }

        $this->components->info('Starting migration from Spatie Laravel Permission to Mandate...');
        $this->newLine();

        try {
            if (! $this->option('skip-permissions')) {
                $this->migratePermissions();
            }

            if (! $this->option('skip-roles')) {
                $this->migrateRoles();
            }

            if (! $this->option('skip-assignments')) {
                $this->migrateRolePermissions();
                $this->migrateUserRoles();
                $this->migrateUserPermissions();
            }

            if ($this->option('create-capabilities')) {
                $this->createCapabilitiesFromPrefixes();
            }

            if ($this->option('convert-permission-sets')) {
                $this->convertPermissionSets();
            }
        } catch (Exception $e) {
            $this->components->error('Migration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->displaySummary();

        if ($this->dryRun) {
            $this->newLine();
            $this->components->info('Run without --dry-run to apply these changes.');
        }

        return self::SUCCESS;
    }

    private function spatieTablesExist(): bool
    {
        return Schema::hasTable('permissions')
            && Schema::hasTable('roles')
            && Schema::hasTable('model_has_permissions')
            && Schema::hasTable('model_has_roles')
            && Schema::hasTable('role_has_permissions');
    }

    private function mandateTablesExist(): bool
    {
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $rolesTable = config('mandate.tables.roles', 'roles');

        return Schema::hasTable($permissionsTable) && Schema::hasTable($rolesTable);
    }

    private function migratePermissions(): void
    {
        $this->components->task('Migrating permissions', function () {
            // Check if Spatie uses different column name
            $guardColumn = Schema::hasColumn('permissions', 'guard_name') ? 'guard_name' : 'guard';

            $spatiePermissions = DB::table('permissions')->get();

            foreach ($spatiePermissions as $permission) {
                $guard = $permission->$guardColumn ?? 'web';

                // Check if already exists
                $exists = Permission::where('name', $permission->name)
                    ->where('guard', $guard)
                    ->exists();

                if ($exists) {
                    continue;
                }

                if (! $this->dryRun) {
                    Permission::create([
                        'name' => $permission->name,
                        'guard' => $guard,
                    ]);
                }

                $this->permissionsCreated++;
            }

            return true;
        });
    }

    private function migrateRoles(): void
    {
        $this->components->task('Migrating roles', function () {
            $guardColumn = Schema::hasColumn('roles', 'guard_name') ? 'guard_name' : 'guard';

            $spatieRoles = DB::table('roles')->get();

            foreach ($spatieRoles as $role) {
                $guard = $role->$guardColumn ?? 'web';

                $exists = Role::where('name', $role->name)
                    ->where('guard', $guard)
                    ->exists();

                if ($exists) {
                    continue;
                }

                if (! $this->dryRun) {
                    Role::create([
                        'name' => $role->name,
                        'guard' => $guard,
                    ]);
                }

                $this->rolesCreated++;
            }

            return true;
        });
    }

    private function migrateRolePermissions(): void
    {
        $this->components->task('Migrating role-permission assignments', function () {
            $rolePermissions = DB::table('role_has_permissions')->get();

            foreach ($rolePermissions as $rp) {
                $spatieRole = DB::table('roles')->where('id', $rp->role_id)->first();
                $spatiePermission = DB::table('permissions')->where('id', $rp->permission_id)->first();

                if (! $spatieRole || ! $spatiePermission) {
                    continue;
                }

                $guardColumn = Schema::hasColumn('roles', 'guard_name') ? 'guard_name' : 'guard';
                $guard = $spatieRole->$guardColumn ?? 'web';

                $role = Role::where('name', $spatieRole->name)->where('guard', $guard)->first();
                $permission = Permission::where('name', $spatiePermission->name)->where('guard', $guard)->first();

                if (! $role || ! $permission) {
                    continue;
                }

                // Check if already assigned
                if ($role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    continue;
                }

                if (! $this->dryRun) {
                    $role->grantPermission($permission);
                }

                $this->rolePermissionsAssigned++;
            }

            return true;
        });
    }

    private function migrateUserRoles(): void
    {
        $this->components->task('Migrating user-role assignments', function () {
            $modelRoles = DB::table('model_has_roles')->get();

            foreach ($modelRoles as $mr) {
                $spatieRole = DB::table('roles')->where('id', $mr->role_id)->first();

                if (! $spatieRole) {
                    continue;
                }

                $guardColumn = Schema::hasColumn('roles', 'guard_name') ? 'guard_name' : 'guard';
                $guard = $spatieRole->$guardColumn ?? 'web';

                $role = Role::where('name', $spatieRole->name)->where('guard', $guard)->first();

                if (! $role) {
                    continue;
                }

                // Get the model
                $modelClass = $mr->model_type;
                if (! class_exists($modelClass)) {
                    continue;
                }

                /** @var \Illuminate\Database\Eloquent\Model|null $model */
                $model = $modelClass::find($mr->model_id);
                if (! $model || ! method_exists($model, 'assignRole')) {
                    continue;
                }

                // Check if already assigned (method existence verified above)
                // @phpstan-ignore method.notFound
                if ($model->hasRole($role)) {
                    continue;
                }

                if (! $this->dryRun) {
                    // @phpstan-ignore method.notFound
                    $model->assignRole($role);
                }

                $this->userRolesAssigned++;
            }

            return true;
        });
    }

    private function migrateUserPermissions(): void
    {
        $this->components->task('Migrating user-permission assignments', function () {
            $modelPermissions = DB::table('model_has_permissions')->get();

            foreach ($modelPermissions as $mp) {
                $spatiePermission = DB::table('permissions')->where('id', $mp->permission_id)->first();

                if (! $spatiePermission) {
                    continue;
                }

                $guardColumn = Schema::hasColumn('permissions', 'guard_name') ? 'guard_name' : 'guard';
                $guard = $spatiePermission->$guardColumn ?? 'web';

                $permission = Permission::where('name', $spatiePermission->name)->where('guard', $guard)->first();

                if (! $permission) {
                    continue;
                }

                $modelClass = $mp->model_type;
                if (! class_exists($modelClass)) {
                    continue;
                }

                /** @var \Illuminate\Database\Eloquent\Model|null $model */
                $model = $modelClass::find($mp->model_id);
                if (! $model || ! method_exists($model, 'grantPermission')) {
                    continue;
                }

                // Check if already assigned (method existence verified above)
                // @phpstan-ignore method.notFound
                if ($model->hasDirectPermission($permission)) {
                    continue;
                }

                if (! $this->dryRun) {
                    // @phpstan-ignore method.notFound
                    $model->grantPermission($permission);
                }

                $this->userPermissionsAssigned++;
            }

            return true;
        });
    }

    private function createCapabilitiesFromPrefixes(): void
    {
        if (! config('mandate.capabilities.enabled', false)) {
            $this->components->warn('Capabilities feature is not enabled. Skipping capability creation.');
            $this->components->info('Enable it in config/mandate.php: \'capabilities\' => [\'enabled\' => true]');

            return;
        }

        $this->components->task('Creating capabilities from permission prefixes', function () {
            $permissions = Permission::all();

            // Group permissions by prefix (before : or .)
            $grouped = [];
            foreach ($permissions as $permission) {
                if (preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)[:.]/', $permission->name, $matches)) {
                    $prefix = $matches[1];
                    $grouped[$prefix][] = $permission;
                }
            }

            foreach ($grouped as $prefix => $prefixPermissions) {
                $capabilityName = mb_strtolower($prefix).'-management';

                $exists = Capability::where('name', $capabilityName)->exists();

                if ($exists) {
                    continue;
                }

                if (! $this->dryRun) {
                    $capability = Capability::create([
                        'name' => $capabilityName,
                        'guard' => $prefixPermissions[0]->guard ?? 'web',
                    ]);

                    foreach ($prefixPermissions as $permission) {
                        $capability->grantPermission($permission);
                    }
                }

                $this->capabilitiesCreated++;
            }

            return true;
        });
    }

    private function displaySummary(): void
    {
        $this->components->info('Migration Summary:');

        $this->components->twoColumnDetail('Permissions created', (string) $this->permissionsCreated);
        $this->components->twoColumnDetail('Roles created', (string) $this->rolesCreated);
        $this->components->twoColumnDetail('Role-permission assignments', (string) $this->rolePermissionsAssigned);
        $this->components->twoColumnDetail('User-role assignments', (string) $this->userRolesAssigned);
        $this->components->twoColumnDetail('User-permission assignments', (string) $this->userPermissionsAssigned);

        if ($this->option('create-capabilities')) {
            $this->components->twoColumnDetail('Capabilities created', (string) $this->capabilitiesCreated);
        }

        if ($this->option('convert-permission-sets')) {
            $this->components->twoColumnDetail('Permission sets converted', (string) $this->permissionSetsConverted);
        }
    }

    private function convertPermissionSets(): void
    {
        if (! config('mandate.capabilities.enabled', false)) {
            $this->components->warn('Capabilities feature is not enabled. Skipping permission set conversion.');
            $this->components->info('Enable it in config/mandate.php: \'capabilities\' => [\'enabled\' => true]');

            return;
        }

        /** @var string $path */
        $path = $this->option('permission-sets-path') ?: app_path('Permissions');

        if (! is_dir($path)) {
            $this->components->warn("Path not found: {$path}");

            return;
        }

        $this->components->task('Converting PermissionsSet classes to capabilities', function () use ($path) {
            $files = glob($path.'/*.php') ?: [];

            foreach ($files as $file) {
                $this->processPermissionSetFile($file);
            }

            return true;
        });
    }

    private function processPermissionSetFile(string $file): void
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return;
        }

        // Check if this file contains a PermissionsSet attribute
        if (! str_contains($content, 'PermissionsSet')) {
            return;
        }

        // Extract namespace and class name
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
            $fqcn = $namespace ? $namespace.'\\'.$className : $className;

            if (! class_exists($fqcn)) {
                // Try to load the class
                require_once $file;
            }

            if (class_exists($fqcn)) {
                $this->convertClassToCapability($fqcn);
            }
        }
    }

    /**
     * @param  class-string  $className
     */
    private function convertClassToCapability(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes();

        $setName = null;
        $setLabel = null;
        $setDescription = null;
        $guard = 'web';

        // Find PermissionsSet attribute
        foreach ($attributes as $attribute) {
            $name = $attribute->getName();

            // Handle both old namespace patterns
            if (str_ends_with($name, 'PermissionsSet')) {
                $args = $attribute->getArguments();
                $setName = $args[0] ?? $args['name'] ?? null;
                $setLabel = $args['label'] ?? null;
                $setDescription = $args['description'] ?? null;
            }

            if (str_ends_with($name, 'Guard')) {
                $args = $attribute->getArguments();
                $guard = $args[0] ?? $args['name'] ?? 'web';
            }
        }

        if (! $setName) {
            return;
        }

        // Convert set name to capability name (e.g., "users" -> "users-management")
        $capabilityName = mb_strtolower($setName);
        if (! str_ends_with($capabilityName, '-management')) {
            $capabilityName .= '-management';
        }

        // Check if capability already exists
        $exists = Capability::where('name', $capabilityName)
            ->where('guard', $guard)
            ->exists();

        if ($exists) {
            return;
        }

        // Collect all permission constants from the class
        $permissions = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && is_string($constant->getValue())) {
                $permissions[] = $constant->getValue();
            }
        }

        if (empty($permissions)) {
            return;
        }

        if (! $this->dryRun) {
            $capability = Capability::create([
                'name' => $capabilityName,
                'guard' => $guard,
                'label' => $setLabel,
                'description' => $setDescription,
            ]);

            // Assign permissions to the capability
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)
                    ->where('guard', $guard)
                    ->first();

                if ($permission) {
                    $capability->grantPermission($permission);
                }
            }
        }

        $this->permissionSetsConverted++;
    }
}
