<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Illuminate\Console\Command;
use OffloadProject\Mandate\CodeFirst\CapabilityDefinition;
use OffloadProject\Mandate\CodeFirst\DefinitionCache;
use OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer;
use OffloadProject\Mandate\CodeFirst\PermissionDefinition;
use OffloadProject\Mandate\CodeFirst\RoleDefinition;
use OffloadProject\Mandate\Events\CapabilitiesSynced;
use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\MandateRegistrar;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

use function Laravel\Prompts\confirm;

/**
 * Sync code-first permission/role/capability definitions to the database.
 */
final class SyncCommand extends Command
{
    protected $signature = 'mandate:sync
                            {--permissions : Sync only permissions}
                            {--roles : Sync only roles}
                            {--capabilities : Sync only capabilities}
                            {--seed : Seed role-permission and role-capability assignments from config}
                            {--guard= : Sync for specific guard only}
                            {--dry-run : Show what would be synced without making changes}
                            {--force : Skip confirmation in production}';

    protected $description = 'Sync code-first permission/role/capability definitions to the database';

    private int $permissionsCreated = 0;

    private int $permissionsUpdated = 0;

    private int $rolesCreated = 0;

    private int $rolesUpdated = 0;

    private int $capabilitiesCreated = 0;

    private int $capabilitiesUpdated = 0;

    public function handle(
        DefinitionDiscoverer $discoverer,
        DefinitionCache $cache,
        MandateRegistrar $registrar
    ): int {
        $codeFirstEnabled = config('mandate.code_first.enabled', false);
        $seedOnly = $this->option('seed')
            && ! $this->option('permissions')
            && ! $this->option('roles')
            && ! $this->option('capabilities');

        // Allow --seed to work without code-first enabled
        if (! $codeFirstEnabled && ! $seedOnly) {
            $this->components->error('Code-first mode is not enabled. Set mandate.code_first.enabled to true.');

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('force') && ! $this->option('dry-run')) {
            if (! confirm('You are about to sync definitions in production. Continue?', false)) {
                $this->components->info('Sync cancelled.');

                return self::SUCCESS;
            }
        }

        $isDryRun = (bool) $this->option('dry-run');
        /** @var string|null $guard */
        $guard = $this->option('guard') ?: null;
        // Sync all if no specific flags passed, or if --seed is used with code-first enabled
        // This ensures discovered permissions are synced before seeding assignments
        $syncAll = ! $this->option('permissions') && ! $this->option('roles') && ! $this->option('capabilities')
            && (! $seedOnly || $codeFirstEnabled);

        if ($isDryRun) {
            $this->components->info('Dry run mode - no changes will be made.');
        }

        // Determine what to sync (skip if seed-only mode)
        $syncPermissions = $codeFirstEnabled && ($syncAll || $this->option('permissions'));
        $syncRoles = $codeFirstEnabled && ($syncAll || $this->option('roles'));
        $syncCapabilities = $codeFirstEnabled && ($syncAll || $this->option('capabilities')) && config('mandate.capabilities.enabled', false);

        // Discover and sync
        if ($syncPermissions) {
            $this->syncPermissions($discoverer, $guard, $isDryRun);
        }

        if ($syncRoles) {
            $this->syncRoles($discoverer, $guard, $isDryRun);
        }

        if ($syncCapabilities) {
            $this->syncCapabilities($discoverer, $guard, $isDryRun);
        }

        // Seed assignments if requested
        if ($this->option('seed') && ! $isDryRun) {
            $this->seedAssignments($guard);
        }

        // Clear caches
        if (! $isDryRun) {
            $cache->forget();
            $registrar->forgetCachedPermissions();
        }

        // Display summary
        $this->displaySummary($isDryRun);

        // Dispatch events
        if (! $isDryRun) {
            $this->dispatchEvents($syncPermissions, $syncRoles, $syncCapabilities);
        }

        return self::SUCCESS;
    }

    /**
     * Sync permission definitions to the database.
     */
    private function syncPermissions(DefinitionDiscoverer $discoverer, ?string $guard, bool $isDryRun): void
    {
        $paths = config('mandate.code_first.paths.permissions', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverPermissions($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (PermissionDefinition $d) => $d->guard === $guard);
        }

        if ($definitions->isEmpty()) {
            $this->components->info('No permission definitions found.');

            return;
        }

        $this->components->info("Found {$definitions->count()} permission definition(s).");

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);
        $hasLabelColumn = $permissionClass::hasLabelColumn();

        foreach ($definitions as $definition) {
            $this->syncPermission($definition, $permissionClass, $hasLabelColumn, $isDryRun);
        }
    }

    /**
     * Sync a single permission definition.
     *
     * @param  class-string<Permission>  $permissionClass
     */
    private function syncPermission(
        PermissionDefinition $definition,
        string $permissionClass,
        bool $hasLabelColumn,
        bool $isDryRun
    ): void {
        /** @var Permission|null $existing */
        $existing = $permissionClass::query()
            ->where('name', $definition->name)
            ->where('guard', $definition->guard)
            ->first();

        if ($existing) {
            // Check if update needed
            $needsUpdate = false;
            $updates = [];

            if ($hasLabelColumn) {
                if ($definition->label !== null && $existing->label !== $definition->label) {
                    $updates['label'] = $definition->label;
                    $needsUpdate = true;
                }
                if ($definition->description !== null && $existing->description !== $definition->description) {
                    $updates['description'] = $definition->description;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                if ($isDryRun) {
                    $this->components->twoColumnDetail(
                        "  <fg=yellow>UPDATE</> {$definition->name}",
                        implode(', ', array_keys($updates))
                    );
                } else {
                    $existing->update($updates);
                }
                $this->permissionsUpdated++;
            }
        } else {
            if ($isDryRun) {
                $this->components->twoColumnDetail(
                    "  <fg=green>CREATE</> {$definition->name}",
                    $definition->guard
                );
            } else {
                $attributes = [
                    'name' => $definition->name,
                    'guard' => $definition->guard,
                ];

                if ($hasLabelColumn) {
                    $attributes['label'] = $definition->label;
                    $attributes['description'] = $definition->description;
                }

                $permissionClass::query()->create($attributes);
            }
            $this->permissionsCreated++;
        }
    }

    /**
     * Sync role definitions to the database.
     */
    private function syncRoles(DefinitionDiscoverer $discoverer, ?string $guard, bool $isDryRun): void
    {
        $paths = config('mandate.code_first.paths.roles', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverRoles($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (RoleDefinition $d) => $d->guard === $guard);
        }

        if ($definitions->isEmpty()) {
            $this->components->info('No role definitions found.');

            return;
        }

        $this->components->info("Found {$definitions->count()} role definition(s).");

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);
        $hasLabelColumn = $roleClass::hasLabelColumn();

        foreach ($definitions as $definition) {
            $this->syncRole($definition, $roleClass, $hasLabelColumn, $isDryRun);
        }
    }

    /**
     * Sync a single role definition.
     *
     * @param  class-string<Role>  $roleClass
     */
    private function syncRole(
        RoleDefinition $definition,
        string $roleClass,
        bool $hasLabelColumn,
        bool $isDryRun
    ): void {
        /** @var Role|null $existing */
        $existing = $roleClass::query()
            ->where('name', $definition->name)
            ->where('guard', $definition->guard)
            ->first();

        if ($existing) {
            $needsUpdate = false;
            $updates = [];

            if ($hasLabelColumn) {
                if ($definition->label !== null && $existing->label !== $definition->label) {
                    $updates['label'] = $definition->label;
                    $needsUpdate = true;
                }
                if ($definition->description !== null && $existing->description !== $definition->description) {
                    $updates['description'] = $definition->description;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                if ($isDryRun) {
                    $this->components->twoColumnDetail(
                        "  <fg=yellow>UPDATE</> {$definition->name}",
                        implode(', ', array_keys($updates))
                    );
                } else {
                    $existing->update($updates);
                }
                $this->rolesUpdated++;
            }
        } else {
            if ($isDryRun) {
                $this->components->twoColumnDetail(
                    "  <fg=green>CREATE</> {$definition->name}",
                    $definition->guard
                );
            } else {
                $attributes = [
                    'name' => $definition->name,
                    'guard' => $definition->guard,
                ];

                if ($hasLabelColumn) {
                    $attributes['label'] = $definition->label;
                    $attributes['description'] = $definition->description;
                }

                $roleClass::query()->create($attributes);
            }
            $this->rolesCreated++;
        }
    }

    /**
     * Sync capability definitions to the database.
     */
    private function syncCapabilities(DefinitionDiscoverer $discoverer, ?string $guard, bool $isDryRun): void
    {
        $paths = config('mandate.code_first.paths.capabilities', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverCapabilities($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (CapabilityDefinition $d) => $d->guard === $guard);
        }

        if ($definitions->isEmpty()) {
            $this->components->info('No capability definitions found.');

            return;
        }

        $this->components->info("Found {$definitions->count()} capability definition(s).");

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);
        $hasLabelColumn = $capabilityClass::hasLabelColumn();

        foreach ($definitions as $definition) {
            $this->syncCapability($definition, $capabilityClass, $hasLabelColumn, $isDryRun);
        }
    }

    /**
     * Sync a single capability definition.
     *
     * @param  class-string<Capability>  $capabilityClass
     */
    private function syncCapability(
        CapabilityDefinition $definition,
        string $capabilityClass,
        bool $hasLabelColumn,
        bool $isDryRun
    ): void {
        /** @var Capability|null $existing */
        $existing = $capabilityClass::query()
            ->where('name', $definition->name)
            ->where('guard', $definition->guard)
            ->first();

        if ($existing) {
            $needsUpdate = false;
            $updates = [];

            if ($hasLabelColumn) {
                if ($definition->label !== null && $existing->label !== $definition->label) {
                    $updates['label'] = $definition->label;
                    $needsUpdate = true;
                }
                if ($definition->description !== null && $existing->description !== $definition->description) {
                    $updates['description'] = $definition->description;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                if ($isDryRun) {
                    $this->components->twoColumnDetail(
                        "  <fg=yellow>UPDATE</> {$definition->name}",
                        implode(', ', array_keys($updates))
                    );
                } else {
                    $existing->update($updates);
                }
                $this->capabilitiesUpdated++;
            }
        } else {
            if ($isDryRun) {
                $this->components->twoColumnDetail(
                    "  <fg=green>CREATE</> {$definition->name}",
                    $definition->guard
                );
            } else {
                $attributes = [
                    'name' => $definition->name,
                    'guard' => $definition->guard,
                ];

                if ($hasLabelColumn) {
                    $attributes['label'] = $definition->label;
                    $attributes['description'] = $definition->description;
                }

                $capabilityClass::query()->create($attributes);
            }
            $this->capabilitiesCreated++;
        }
    }

    /**
     * Seed role-permission and role-capability assignments from config.
     */
    private function seedAssignments(?string $guard): void
    {
        $assignments = config('mandate.assignments', []);

        if (empty($assignments)) {
            return;
        }

        $this->components->info('Seeding role assignments...');

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);
        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);
        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        foreach ($assignments as $roleName => $assignment) {
            $roleGuard = $guard ?? config('auth.defaults.guard', 'web');

            /** @var Role|null $role */
            $role = $roleClass::query()
                ->where('name', $roleName)
                ->where('guard', $roleGuard)
                ->first();

            if ($role === null) {
                $role = $roleClass::create([
                    'name' => $roleName,
                    'guard' => $roleGuard,
                ]);
                $this->components->twoColumnDetail(
                    '  <fg=green>Created role</>',
                    $roleName
                );
            }

            // Sync permissions
            if (! empty($assignment['permissions'])) {
                /** @var array<string> $permissionNames */
                $permissionNames = $assignment['permissions'];
                $permissionIds = [];

                foreach ($permissionNames as $permissionName) {
                    /** @var Permission|null $permission */
                    $permission = $permissionClass::query()
                        ->where('name', $permissionName)
                        ->where('guard', $roleGuard)
                        ->first();

                    if ($permission === null) {
                        $permission = $permissionClass::create([
                            'name' => $permissionName,
                            'guard' => $roleGuard,
                        ]);
                        $this->components->twoColumnDetail(
                            '  <fg=green>Created permission</>',
                            $permissionName
                        );
                    }

                    $permissionIds[] = $permission->getKey();
                }

                if (! empty($permissionIds)) {
                    $role->permissions()->syncWithoutDetaching($permissionIds);
                    $this->components->twoColumnDetail(
                        "  {$roleName}",
                        count($permissionIds).' permission(s)'
                    );
                }
            }

            // Sync capabilities
            if (config('mandate.capabilities.enabled', false) && ! empty($assignment['capabilities'])) {
                /** @var array<string> $capabilityNames */
                $capabilityNames = $assignment['capabilities'];
                $capabilityIds = [];

                foreach ($capabilityNames as $capabilityName) {
                    /** @var Capability|null $capability */
                    $capability = $capabilityClass::query()
                        ->where('name', $capabilityName)
                        ->where('guard', $roleGuard)
                        ->first();

                    if ($capability === null) {
                        $capability = $capabilityClass::create([
                            'name' => $capabilityName,
                            'guard' => $roleGuard,
                        ]);
                        $this->components->twoColumnDetail(
                            '  <fg=green>Created capability</>',
                            $capabilityName
                        );
                    }

                    $capabilityIds[] = $capability->getKey();
                }

                if (! empty($capabilityIds)) {
                    $role->capabilities()->syncWithoutDetaching($capabilityIds);
                    $this->components->twoColumnDetail(
                        "  {$roleName}",
                        count($capabilityIds).' capability(ies)'
                    );
                }
            }
        }
    }

    /**
     * Display sync summary.
     */
    private function displaySummary(bool $isDryRun): void
    {
        $this->newLine();

        $prefix = $isDryRun ? 'Would have synced' : 'Synced';

        if ($this->permissionsCreated > 0 || $this->permissionsUpdated > 0) {
            $this->components->twoColumnDetail(
                'Permissions',
                "{$this->permissionsCreated} created, {$this->permissionsUpdated} updated"
            );
        }

        if ($this->rolesCreated > 0 || $this->rolesUpdated > 0) {
            $this->components->twoColumnDetail(
                'Roles',
                "{$this->rolesCreated} created, {$this->rolesUpdated} updated"
            );
        }

        if ($this->capabilitiesCreated > 0 || $this->capabilitiesUpdated > 0) {
            $this->components->twoColumnDetail(
                'Capabilities',
                "{$this->capabilitiesCreated} created, {$this->capabilitiesUpdated} updated"
            );
        }

        $total = $this->permissionsCreated + $this->permissionsUpdated
            + $this->rolesCreated + $this->rolesUpdated
            + $this->capabilitiesCreated + $this->capabilitiesUpdated;

        if ($total === 0) {
            $this->components->info('Nothing to sync - all definitions are up to date.');
        } else {
            $this->components->success("{$prefix} {$total} definition(s).");
        }
    }

    /**
     * Dispatch sync events.
     */
    private function dispatchEvents(bool $syncedPermissions, bool $syncedRoles, bool $syncedCapabilities): void
    {
        $permissionsEvent = new PermissionsSynced(
            $this->permissionsCreated,
            $this->permissionsUpdated,
            collect() // We could collect names here if needed
        );

        $rolesEvent = new RolesSynced(
            $this->rolesCreated,
            $this->rolesUpdated,
            collect()
        );

        $capabilitiesEvent = $syncedCapabilities
            ? new CapabilitiesSynced(
                $this->capabilitiesCreated,
                $this->capabilitiesUpdated,
                collect()
            )
            : null;

        if ($syncedPermissions) {
            PermissionsSynced::dispatch(
                $this->permissionsCreated,
                $this->permissionsUpdated,
                collect()
            );
        }

        if ($syncedRoles) {
            RolesSynced::dispatch(
                $this->rolesCreated,
                $this->rolesUpdated,
                collect()
            );
        }

        if ($capabilitiesEvent !== null) {
            CapabilitiesSynced::dispatch(
                $this->capabilitiesCreated,
                $this->capabilitiesUpdated,
                collect()
            );
        }

        MandateSynced::dispatch($permissionsEvent, $rolesEvent, $capabilitiesEvent);
    }
}
