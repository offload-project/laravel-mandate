<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\CodeFirst\CapabilityDefinition;
use OffloadProject\Mandate\CodeFirst\DefinitionCache;
use OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer;
use OffloadProject\Mandate\CodeFirst\PermissionDefinition;
use OffloadProject\Mandate\CodeFirst\RoleDefinition;
use OffloadProject\Mandate\Concerns\HasRoles;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Events\CapabilitiesSynced;
use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use RuntimeException;

/**
 * Main service class for Mandate.
 *
 * Provides a convenient API for working with permissions and roles.
 *
 * @example
 * // Via Facade
 * Mandate::hasPermission($subject, 'article:edit');
 * Mandate::hasRole($subject, 'admin');
 * Mandate::getPermissions($subject);
 * Mandate::getRoles($subject);
 *
 * // Fluent authorization builder
 * Mandate::for($user)->can('edit-articles');
 * Mandate::for($user)->hasRole('admin')->orHasPermission('edit')->check();
 *
 * // Create permissions and roles
 * Mandate::createPermission('article:edit');
 * Mandate::createRole('admin');
 */
final class Mandate
{
    public function __construct(
        private readonly MandateRegistrar $registrar
    ) {}

    /**
     * Create a fluent authorization builder for a subject.
     *
     * @example
     * Mandate::for($user)->can('edit-articles');
     * Mandate::for($user)->is('admin');
     * Mandate::for($user)->hasRole('admin')->orHasPermission('edit')->check();
     * Mandate::for($user)->inContext($team)->hasPermission('manage')->check();
     */
    public function for(Model $subject): AuthorizationBuilder
    {
        return new AuthorizationBuilder($subject);
    }

    /**
     * Check if context model support is enabled.
     */
    public function contextEnabled(): bool
    {
        return (bool) config('mandate.context.enabled', false);
    }

    /**
     * Check if feature integration is enabled.
     */
    public function featureIntegrationEnabled(): bool
    {
        return (bool) config('mandate.features.enabled', false)
            && $this->contextEnabled();
    }

    /**
     * Check if the given model is a Feature context.
     */
    public function isFeatureContext(?Model $context): bool
    {
        if ($context === null) {
            return false;
        }

        $featureModels = config('mandate.features.models', []);

        if (empty($featureModels)) {
            return false;
        }

        foreach ($featureModels as $featureModel) {
            if ($context instanceof $featureModel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the feature access handler.
     */
    public function getFeatureAccessHandler(): ?FeatureAccessHandler
    {
        if (! app()->bound(FeatureAccessHandler::class)) {
            return null;
        }

        return app(FeatureAccessHandler::class);
    }

    /**
     * Check if a feature is globally active.
     */
    public function isFeatureActive(Model $feature): bool
    {
        if (! $this->featureIntegrationEnabled()) {
            return true;
        }

        if (! $this->isFeatureContext($feature)) {
            return true;
        }

        $handler = $this->getFeatureAccessHandler();

        if ($handler === null) {
            return $this->handleMissingFeatureHandler();
        }

        return $handler->isActive($feature);
    }

    /**
     * Check if a subject has access to a feature.
     */
    public function hasFeatureAccess(Model $feature, Model $subject): bool
    {
        if (! $this->featureIntegrationEnabled()) {
            return true;
        }

        if (! $this->isFeatureContext($feature)) {
            return true;
        }

        $handler = $this->getFeatureAccessHandler();

        if ($handler === null) {
            return $this->handleMissingFeatureHandler();
        }

        return $handler->hasAccess($feature, $subject);
    }

    /**
     * Check both feature activation and subject access.
     *
     * This is a convenience method that combines isActive() and hasAccess()
     * checks. Returns true only if the feature is globally active AND
     * the subject has been granted access.
     */
    public function canAccessFeature(Model $feature, Model $subject): bool
    {
        if (! $this->featureIntegrationEnabled()) {
            return true;
        }

        if (! $this->isFeatureContext($feature)) {
            return true;
        }

        $handler = $this->getFeatureAccessHandler();

        if ($handler === null) {
            return $this->handleMissingFeatureHandler();
        }

        return $handler->canAccess($feature, $subject);
    }

    /**
     * Check if a model has a specific permission.
     *
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasPermission(Model $subject, string $permission, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasPermission')) {
            return false;
        }

        return $subject->hasPermission($permission, $context);
    }

    /**
     * Check if a model has any of the given permissions.
     *
     * @param  array<string>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAnyPermission(Model $subject, array $permissions, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAnyPermission')) {
            return false;
        }

        return $subject->hasAnyPermission($permissions, $context);
    }

    /**
     * Check if a model has all of the given permissions.
     *
     * @param  array<string>  $permissions
     * @param  Model|null  $context  Optional context model for scoped permission check
     */
    public function hasAllPermissions(Model $subject, array $permissions, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAllPermissions')) {
            return false;
        }

        return $subject->hasAllPermissions($permissions, $context);
    }

    /**
     * Check if a model has a specific role.
     *
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasRole(Model $subject, string $role, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasRole')) {
            return false;
        }

        return $subject->hasRole($role, $context);
    }

    /**
     * Check if a model has any of the given roles.
     *
     * @param  array<string>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasAnyRole(Model $subject, array $roles, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAnyRole')) {
            return false;
        }

        return $subject->hasAnyRole($roles, $context);
    }

    /**
     * Check if a model has all of the given roles.
     *
     * @param  array<string>  $roles
     * @param  Model|null  $context  Optional context model for scoped role check
     */
    public function hasAllRoles(Model $subject, array $roles, ?Model $context = null): bool
    {
        if (! method_exists($subject, 'hasAllRoles')) {
            return false;
        }

        return $subject->hasAllRoles($roles, $context);
    }

    /**
     * Get all permissions for a model.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, PermissionContract>
     */
    public function getPermissions(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getAllPermissions')) {
            return collect();
        }

        return $subject->getAllPermissions($context);
    }

    /**
     * Get permission names for a model.
     *
     * @param  Model|null  $context  Optional context model to filter permissions
     * @return Collection<int, string>
     */
    public function getPermissionNames(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getPermissionNames')) {
            return collect();
        }

        return $subject->getPermissionNames($context);
    }

    /**
     * Get all roles for a model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, RoleContract>
     */
    public function getRoles(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getRolesForContext')) {
            if (! property_exists($subject, 'roles') && ! method_exists($subject, 'roles')) {
                return collect();
            }

            /* @var $subject HasRoles */
            return $subject->roles;
        }

        return $subject->getRolesForContext($context);
    }

    /**
     * Get role names for a model.
     *
     * @param  Model|null  $context  Optional context model to filter roles
     * @return Collection<int, string>
     */
    public function getRoleNames(Model $subject, ?Model $context = null): Collection
    {
        if (! method_exists($subject, 'getRoleNames')) {
            return collect();
        }

        return $subject->getRoleNames($context);
    }

    /**
     * Get all contexts where a subject has a specific role.
     *
     * @return Collection<int, Model>
     */
    public function getRoleContexts(Model $subject, string $role): Collection
    {
        if (! method_exists($subject, 'getRoleContexts')) {
            return collect();
        }

        return $subject->getRoleContexts($role);
    }

    /**
     * Get all contexts where a subject has a specific permission.
     *
     * @return Collection<int, Model>
     */
    public function getPermissionContexts(Model $subject, string $permission): Collection
    {
        if (! method_exists($subject, 'getPermissionContexts')) {
            return collect();
        }

        return $subject->getPermissionContexts($permission);
    }

    /**
     * Create a new permission.
     */
    public function createPermission(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        return $permissionClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a permission.
     */
    public function findOrCreatePermission(string $name, ?string $guard = null): PermissionContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);

        return $permissionClass::findOrCreate($name, $guard);
    }

    /**
     * Create a new role.
     */
    public function createRole(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        return $roleClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a role.
     */
    public function findOrCreateRole(string $name, ?string $guard = null): RoleContract
    {
        $guard ??= Guard::getDefaultName();

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);

        return $roleClass::findOrCreate($name, $guard);
    }

    /**
     * Get all registered permissions.
     *
     * @return Collection<int, Permission>
     */
    public function getAllPermissions(?string $guard = null): Collection
    {
        if ($guard !== null) {
            return $this->registrar->getPermissionsForGuard($guard);
        }

        return $this->registrar->getPermissions();
    }

    /**
     * Get all registered roles.
     *
     * @return Collection<int, Role>
     */
    public function getAllRoles(?string $guard = null): Collection
    {
        if ($guard !== null) {
            return $this->registrar->getRolesForGuard($guard);
        }

        return $this->registrar->getRoles();
    }

    /**
     * Check if capabilities feature is enabled.
     */
    public function capabilitiesEnabled(): bool
    {
        return (bool) config('mandate.capabilities.enabled', false);
    }

    /**
     * Check if a model has a specific capability.
     */
    public function hasCapability(Model $subject, string $capability): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasCapability')) {
            return false;
        }

        return $subject->hasCapability($capability);
    }

    /**
     * Check if a model has any of the given capabilities.
     *
     * @param  array<string>  $capabilities
     */
    public function hasAnyCapability(Model $subject, array $capabilities): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasAnyCapability')) {
            return false;
        }

        return $subject->hasAnyCapability($capabilities);
    }

    /**
     * Check if a model has all of the given capabilities.
     *
     * @param  array<string>  $capabilities
     */
    public function hasAllCapabilities(Model $subject, array $capabilities): bool
    {
        if (! $this->capabilitiesEnabled()) {
            return false;
        }

        if (! method_exists($subject, 'hasAllCapabilities')) {
            return false;
        }

        return $subject->hasAllCapabilities($capabilities);
    }

    /**
     * Get all capabilities for a model.
     *
     * @return Collection<int, CapabilityContract>
     */
    public function getCapabilities(Model $subject): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if (! method_exists($subject, 'getAllCapabilities')) {
            return collect();
        }

        return $subject->getAllCapabilities();
    }

    /**
     * Get capability names for a model.
     *
     * @return Collection<int, string>
     */
    public function getCapabilityNames(Model $subject): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if (! method_exists($subject, 'getAllCapabilities')) {
            return collect();
        }

        return $subject->getAllCapabilities()->pluck('name');
    }

    /**
     * Create a new capability.
     */
    public function createCapability(string $name, ?string $guard = null): CapabilityContract
    {
        if (! $this->capabilitiesEnabled()) {
            throw new RuntimeException('Capabilities feature is not enabled. Set mandate.capabilities.enabled to true in your configuration.');
        }

        $guard ??= Guard::getDefaultName();

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        return $capabilityClass::create([
            'name' => $name,
            'guard' => $guard,
        ]);
    }

    /**
     * Find or create a capability.
     */
    public function findOrCreateCapability(string $name, ?string $guard = null): CapabilityContract
    {
        if (! $this->capabilitiesEnabled()) {
            throw new RuntimeException('Capabilities feature is not enabled. Set mandate.capabilities.enabled to true in your configuration.');
        }

        $guard ??= Guard::getDefaultName();

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);

        return $capabilityClass::findOrCreate($name, $guard);
    }

    /**
     * Get all registered capabilities.
     *
     * @return Collection<int, Capability>
     */
    public function getAllCapabilities(?string $guard = null): Collection
    {
        if (! $this->capabilitiesEnabled()) {
            return collect();
        }

        if ($guard !== null) {
            return $this->registrar->getCapabilitiesForGuard($guard);
        }

        return $this->registrar->getCapabilities();
    }

    /**
     * Clear the permission cache.
     */
    public function clearCache(): bool
    {
        return $this->registrar->forgetCachedPermissions();
    }

    /**
     * Get the registrar instance.
     */
    public function getRegistrar(): MandateRegistrar
    {
        return $this->registrar;
    }

    /**
     * Get authorization data for sharing with frontend.
     *
     * @param  Model|null  $context  Optional context model to filter authorization data
     * @return array{permissions: array<string>, roles: array<string>, capabilities?: array<string>}
     */
    public function getAuthorizationData(?Authenticatable $subject = null, ?Model $context = null): array
    {
        $subject ??= auth()->user();

        if ($subject === null || ! $subject instanceof Model) {
            $data = [
                'permissions' => [],
                'roles' => [],
            ];

            if ($this->capabilitiesEnabled()) {
                $data['capabilities'] = [];
            }

            return $data;
        }

        $data = [
            'permissions' => $this->getPermissionNames($subject, $context)->toArray(),
            'roles' => $this->getRoleNames($subject, $context)->toArray(),
        ];

        if ($this->capabilitiesEnabled()) {
            $data['capabilities'] = $this->getCapabilityNames($subject)->toArray();
        }

        return $data;
    }

    /**
     * Sync code-first definitions to the database and optionally seed assignments.
     *
     * This method programmatically performs the same operations as the `mandate:sync` command.
     *
     * @param  bool  $permissions  Sync only permissions (if true without roles/capabilities, only permissions are synced)
     * @param  bool  $roles  Sync only roles (if true without permissions/capabilities, only roles are synced)
     * @param  bool  $capabilities  Sync only capabilities (if true without permissions/roles, only capabilities are synced)
     * @param  bool  $seed  Seed role-permission and role-capability assignments from config
     * @param  string|null  $guard  Sync for a specific guard only
     *
     * @throws RuntimeException If code-first mode is not enabled (unless using seed-only mode)
     */
    public function sync(
        bool $permissions = false,
        bool $roles = false,
        bool $capabilities = false,
        bool $seed = false,
        ?string $guard = null,
    ): SyncResult {
        $codeFirstEnabled = (bool) config('mandate.code_first.enabled', false);
        $seedOnly = $seed && ! $permissions && ! $roles && ! $capabilities;

        // Allow seed to work without code-first enabled
        if (! $codeFirstEnabled && ! $seedOnly) {
            throw new RuntimeException('Code-first mode is not enabled. Set mandate.code_first.enabled to true.');
        }

        $discoverer = app(DefinitionDiscoverer::class);
        $cache = app(DefinitionCache::class);

        $permissionsCreated = 0;
        $permissionsUpdated = 0;
        $rolesCreated = 0;
        $rolesUpdated = 0;
        $capabilitiesCreated = 0;
        $capabilitiesUpdated = 0;

        $syncAll = ! $permissions && ! $roles && ! $capabilities && ! $seedOnly;

        // Determine what to sync (skip if seed-only mode)
        $syncPermissions = $codeFirstEnabled && ($syncAll || $permissions);
        $syncRoles = $codeFirstEnabled && ($syncAll || $roles);
        $syncCapabilities = $codeFirstEnabled && ($syncAll || $capabilities) && $this->capabilitiesEnabled();

        // Sync permissions
        if ($syncPermissions) {
            $result = $this->syncPermissionsFromDefinitions($discoverer, $guard);
            $permissionsCreated = $result['created'];
            $permissionsUpdated = $result['updated'];
        }

        // Sync roles
        if ($syncRoles) {
            $result = $this->syncRolesFromDefinitions($discoverer, $guard);
            $rolesCreated = $result['created'];
            $rolesUpdated = $result['updated'];
        }

        // Sync capabilities
        if ($syncCapabilities) {
            $result = $this->syncCapabilitiesFromDefinitions($discoverer, $guard);
            $capabilitiesCreated = $result['created'];
            $capabilitiesUpdated = $result['updated'];
        }

        // Seed assignments if requested
        $assignmentsSeeded = false;
        if ($seed) {
            $this->seedAssignmentsFromConfig($guard);
            $assignmentsSeeded = true;
        }

        // Clear caches
        $cache->forget();
        $this->registrar->forgetCachedPermissions();

        // Dispatch events
        if ($syncPermissions) {
            PermissionsSynced::dispatch($permissionsCreated, $permissionsUpdated, collect());
        }

        if ($syncRoles) {
            RolesSynced::dispatch($rolesCreated, $rolesUpdated, collect());
        }

        if ($syncCapabilities) {
            CapabilitiesSynced::dispatch($capabilitiesCreated, $capabilitiesUpdated, collect());
        }

        $permissionsEvent = new PermissionsSynced($permissionsCreated, $permissionsUpdated, collect());
        $rolesEvent = new RolesSynced($rolesCreated, $rolesUpdated, collect());
        $capabilitiesEvent = $syncCapabilities
            ? new CapabilitiesSynced($capabilitiesCreated, $capabilitiesUpdated, collect())
            : null;

        MandateSynced::dispatch($permissionsEvent, $rolesEvent, $capabilitiesEvent);

        return new SyncResult(
            permissionsCreated: $permissionsCreated,
            permissionsUpdated: $permissionsUpdated,
            rolesCreated: $rolesCreated,
            rolesUpdated: $rolesUpdated,
            capabilitiesCreated: $capabilitiesCreated,
            capabilitiesUpdated: $capabilitiesUpdated,
            assignmentsSeeded: $assignmentsSeeded,
        );
    }

    /**
     * Sync permission definitions to the database.
     *
     * @return array{created: int, updated: int}
     */
    private function syncPermissionsFromDefinitions(DefinitionDiscoverer $discoverer, ?string $guard): array
    {
        $paths = config('mandate.code_first.paths.permissions', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverPermissions($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (PermissionDefinition $d) => $d->guard === $guard);
        }

        /** @var class-string<Permission> $permissionClass */
        $permissionClass = config('mandate.models.permission', Permission::class);
        $hasLabelColumn = $permissionClass::hasLabelColumn();

        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
            /** @var Permission|null $existing */
            $existing = $permissionClass::query()
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
                    $existing->update($updates);
                    $updated++;
                }
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
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Sync role definitions to the database.
     *
     * @return array{created: int, updated: int}
     */
    private function syncRolesFromDefinitions(DefinitionDiscoverer $discoverer, ?string $guard): array
    {
        $paths = config('mandate.code_first.paths.roles', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverRoles($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (RoleDefinition $d) => $d->guard === $guard);
        }

        /** @var class-string<Role> $roleClass */
        $roleClass = config('mandate.models.role', Role::class);
        $hasLabelColumn = $roleClass::hasLabelColumn();

        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
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
                    $existing->update($updates);
                    $updated++;
                }
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
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Sync capability definitions to the database.
     *
     * @return array{created: int, updated: int}
     */
    private function syncCapabilitiesFromDefinitions(DefinitionDiscoverer $discoverer, ?string $guard): array
    {
        $paths = config('mandate.code_first.paths.capabilities', []);
        $paths = is_array($paths) ? $paths : [$paths];

        $definitions = $discoverer->discoverCapabilities($paths);

        if ($guard !== null) {
            $definitions = $definitions->filter(fn (CapabilityDefinition $d) => $d->guard === $guard);
        }

        /** @var class-string<Capability> $capabilityClass */
        $capabilityClass = config('mandate.models.capability', Capability::class);
        $hasLabelColumn = $capabilityClass::hasLabelColumn();

        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
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
                    $existing->update($updates);
                    $updated++;
                }
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
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Seed role-permission and role-capability assignments from config.
     */
    private function seedAssignmentsFromConfig(?string $guard): void
    {
        $assignments = config('mandate.assignments', []);

        if (empty($assignments)) {
            return;
        }

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
                    }

                    $permissionIds[] = $permission->getKey();
                }

                if (! empty($permissionIds)) {
                    $role->permissions()->syncWithoutDetaching($permissionIds);
                }
            }

            // Sync capabilities
            if ($this->capabilitiesEnabled() && ! empty($assignment['capabilities'])) {
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
                    }

                    $capabilityIds[] = $capability->getKey();
                }

                if (! empty($capabilityIds)) {
                    $role->capabilities()->syncWithoutDetaching($capabilityIds);
                }
            }
        }
    }

    /**
     * Handle the case when feature handler is not available.
     */
    private function handleMissingFeatureHandler(): bool
    {
        $behavior = config('mandate.features.on_missing_handler', 'deny');

        return match ($behavior) {
            'allow' => true,
            default => false, // 'deny'
        };
    }
}
