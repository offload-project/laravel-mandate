<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Concerns\CachesRegistryData;
use OffloadProject\Mandate\Concerns\DiscoversClasses;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Support\PennantHelper;
use OffloadProject\Mandate\Support\WildcardMatcher;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Discovers and manages roles from class constants and configuration.
 */
final class RoleRegistry implements RoleRegistryContract
{
    /** @use CachesRegistryData<RoleData> */
    use CachesRegistryData;

    use DiscoversClasses;

    public const CACHE_KEY = 'mandate.roles';

    /** @var array<string, string>|null Map of role name to feature class */
    private ?array $featureMap = null;

    public function __construct(
        private readonly FeatureRegistryContract $featureRegistry,
        private readonly RoleHierarchyResolver $hierarchyResolver,
    ) {}

    /**
     * Get all discovered roles.
     *
     * @return Collection<int, RoleData>
     */
    public function all(): Collection
    {
        return $this->getCachedData();
    }

    /**
     * Get roles with active status for a specific model.
     *
     * Uses batch feature flag checking and preloaded roles for improved performance.
     *
     * @return Collection<int, RoleData>
     */
    public function forModel(Model $model): Collection
    {
        $roles = $this->all();

        // Batch check all unique features for better performance
        $featureStatuses = $this->batchCheckFeatures($model, $roles);

        // Preload assigned role names once to avoid N+1 queries
        $assignedNames = $this->getAssignedRoleNames($model);

        return $roles->map(function (RoleData $role) use ($assignedNames, $featureStatuses) {
            $isAssigned = $assignedNames->contains($role->name);

            $featureActive = null;
            if ($role->feature !== null) {
                $featureActive = $featureStatuses[$role->feature] ?? null;
            }

            return $role->withStatus($isAssigned, $featureActive);
        });
    }

    /**
     * Get assigned roles for a model (has role AND feature is active).
     *
     * @return Collection<int, RoleData>
     */
    public function assigned(Model $model): Collection
    {
        return $this->forModel($model)->filter(fn (RoleData $r) => $r->isAssigned());
    }

    /**
     * Get available roles (not gated by feature or feature is active).
     *
     * @return Collection<int, RoleData>
     */
    public function available(Model $model): Collection
    {
        return $this->forModel($model)->filter(fn (RoleData $r) => $r->isAvailable());
    }

    /**
     * Check if a model has a specific role (considering feature flags).
     */
    public function has(Model $model, string $role): bool
    {
        $roleData = $this->forModel($model)->firstWhere('name', $role);

        if ($roleData === null) {
            return method_exists($model, 'assignedRole') && $model->assignedRole($role);
        }

        return $roleData->isAssigned();
    }

    /**
     * Get all role names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return $this->all()->pluck('name')->all();
    }

    /**
     * Get roles grouped by their set name.
     *
     * @return Collection<string, Collection<int, RoleData>>
     */
    public function grouped(): Collection
    {
        return $this->all()->groupBy(fn (RoleData $r) => $r->set ?? 'default');
    }

    /**
     * Get a specific role by name.
     */
    public function find(string $role): ?RoleData
    {
        return $this->all()->firstWhere('name', $role);
    }

    /**
     * Get the feature class that gates a role.
     */
    public function feature(string $role): ?string
    {
        return $this->find($role)?->feature;
    }

    /**
     * Get parent roles for a given role.
     *
     * @return Collection<int, RoleData>
     */
    public function parents(string $role): Collection
    {
        $roleData = $this->find($role);

        if ($roleData === null || empty($roleData->inheritsFrom)) {
            return collect();
        }

        return $this->all()->filter(
            fn (RoleData $r) => in_array($r->name, $roleData->inheritsFrom, true)
        )->values();
    }

    /**
     * Get child roles that inherit from a given role.
     *
     * @return Collection<int, RoleData>
     */
    public function children(string $role): Collection
    {
        return $this->all()->filter(
            fn (RoleData $r) => in_array($role, $r->inheritsFrom, true)
        )->values();
    }

    /**
     * Clear the cached roles.
     */
    public function clearCache(): void
    {
        $this->featureMap = null;
        $this->clearCachedData();
    }

    /**
     * Get the cache key for this registry.
     */
    protected function getCacheKey(): string
    {
        return self::CACHE_KEY;
    }

    /**
     * Hydrate a RoleData from a cached array.
     *
     * @param  array<string, mixed>  $item
     */
    protected function hydrateItem(array $item): RoleData
    {
        return RoleData::fromArray($item);
    }

    /**
     * Discover roles from directories and apply mappings.
     *
     * @return Collection<int, RoleData>
     */
    protected function discover(): Collection
    {
        $roles = $this->discoverFromDirectories(
            'mandate.discovery.roles',
            RoleSet::class,
            fn (string $class) => $this->extractFromClass($class)
        );

        $roles = $this->applyFeatureMappings($roles);
        $roles = $this->applyPermissionMappings($roles);
        $roles = $this->hierarchyResolver->resolve($roles);

        return $roles->unique('name')->values();
    }

    /**
     * Get assigned role names for a model.
     *
     * @return Collection<int, string>
     */
    private function getAssignedRoleNames(Model $model): Collection
    {
        if (method_exists($model, 'roleNames')) {
            /** @var array<string> $names */
            $names = $model->roleNames();

            return collect($names);
        }

        if (method_exists($model, 'roles')) {
            /** @var \Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
            $relation = $model->roles();

            return $relation->pluck('name');
        }

        return collect();
    }

    /**
     * Batch check feature flags for all unique features.
     *
     * @param  Collection<int, RoleData>  $roles
     * @return array<string, bool>
     */
    private function batchCheckFeatures(Model $model, Collection $roles): array
    {
        $features = $roles
            ->pluck('feature')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return PennantHelper::batchCheck($model, $features);
    }

    /**
     * Extract roles from a class with constants.
     *
     * @param  class-string  $class
     * @return Collection<int, RoleData>
     */
    private function extractFromClass(string $class): Collection
    {
        $reflection = new ReflectionClass($class);
        $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

        return collect($constants)
            ->filter(fn (ReflectionClassConstant $const) => is_string($const->getValue()))
            ->map(fn (ReflectionClassConstant $const) => RoleData::fromClassConstant($class, $const->getName()));
    }

    /**
     * Apply feature mappings from discovered features.
     *
     * @param  Collection<int, RoleData>  $roles
     * @return Collection<int, RoleData>
     */
    private function applyFeatureMappings(Collection $roles): Collection
    {
        $featureMap = $this->getFeatureMap();

        return $roles->map(function (RoleData $role) use ($featureMap) {
            $feature = $featureMap[$role->name] ?? null;

            if ($feature !== null && $role->feature === null) {
                return new RoleData(
                    name: $role->name,
                    label: $role->label,
                    description: $role->description,
                    set: $role->set,
                    guard: $role->guard,
                    scope: $role->scope,
                    feature: $feature,
                    permissions: $role->permissions,
                    metadata: $role->metadata,
                );
            }

            return $role;
        });
    }

    /**
     * Apply permission mappings from config.
     *
     * @param  Collection<int, RoleData>  $roles
     * @return Collection<int, RoleData>
     */
    private function applyPermissionMappings(Collection $roles): Collection
    {
        $rolePermissions = config('mandate-seed.role_permissions', []);

        return $roles->map(function (RoleData $role) use ($rolePermissions) {
            $configPermissions = $rolePermissions[$role->name] ?? null;

            if ($configPermissions === null) {
                return $role;
            }

            $permissions = $this->resolvePermissions($configPermissions);

            return new RoleData(
                name: $role->name,
                label: $role->label,
                description: $role->description,
                set: $role->set,
                guard: $role->guard,
                scope: $role->scope,
                feature: $role->feature,
                permissions: $permissions,
                metadata: $role->metadata,
            );
        });
    }

    /**
     * Resolve permissions from config (can be permission classes, strings, or wildcards).
     *
     * Supports wildcard patterns like 'users.*' or '*.view' which will be expanded
     * to all matching permissions from the permission registry.
     *
     * @param  array<mixed>  $items
     * @return array<string>
     */
    private function resolvePermissions(array $items): array
    {
        $permissions = [];
        $allPermissionNames = null;

        foreach ($items as $item) {
            if (is_string($item) && class_exists($item)) {
                $reflection = new ReflectionClass($item);
                $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

                foreach ($constants as $const) {
                    $value = $const->getValue();
                    if (is_string($value)) {
                        $permissions[] = $value;
                    }
                }
            } elseif (is_string($item)) {
                if (WildcardMatcher::isWildcard($item)) {
                    if ($allPermissionNames === null) {
                        $allPermissionNames = $this->getAllPermissionNames();
                    }
                    $expanded = WildcardMatcher::expand($item, $allPermissionNames);
                    $permissions = array_merge($permissions, $expanded);
                } else {
                    $permissions[] = $item;
                }
            }
        }

        return $permissions;
    }

    /**
     * Get all permission names from the permission registry.
     *
     * @return array<string>
     */
    private function getAllPermissionNames(): array
    {
        return app(PermissionRegistryContract::class)->names();
    }

    /**
     * Build the feature->role mapping.
     *
     * @return array<string, string>
     */
    private function getFeatureMap(): array
    {
        if ($this->featureMap !== null) {
            return $this->featureMap;
        }

        $this->featureMap = [];

        foreach ($this->featureRegistry->all() as $feature) {
            foreach ($feature->roles as $role) {
                $this->featureMap[$role->name] = $feature->class;
            }
        }

        return $this->featureMap;
    }
}
