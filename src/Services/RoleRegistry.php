<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Concerns\DiscoversClasses;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Data\RoleData;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Discovers and manages roles from class constants and configuration.
 */
final class RoleRegistry implements RoleRegistryContract
{
    use DiscoversClasses;

    /** @var Collection<int, RoleData>|null */
    private ?Collection $cachedRoles = null;

    /** @var array<string, string>|null Map of role name to feature class */
    private ?array $featureMap = null;

    public function __construct(
        private readonly FeatureRegistryContract $featureRegistry,
    ) {}

    /**
     * Get all discovered roles.
     *
     * @return Collection<int, RoleData>
     */
    public function all(): Collection
    {
        if ($this->cachedRoles !== null) {
            return $this->cachedRoles;
        }

        $roles = $this->discoverFromDirectories(
            'mandate.role_directories',
            RoleSet::class,
            fn (string $class) => $this->extractFromClass($class)
        );

        // Apply feature mappings from features
        $roles = $this->applyFeatureMappings($roles);

        // Apply permission mappings from config
        $roles = $this->applyPermissionMappings($roles);

        $this->cachedRoles = $roles->unique('name')->values();

        return $this->cachedRoles;
    }

    /**
     * Get roles with active status for a specific model.
     *
     * Uses batch feature flag checking for improved performance.
     *
     * @return Collection<int, RoleData>
     */
    public function forModel(Model $model): Collection
    {
        $roles = $this->all();

        // Batch check all unique features for better performance
        $featureStatuses = $this->batchCheckFeatures($model, $roles);

        return $roles->map(function (RoleData $role) use ($model, $featureStatuses) {
            // Check if role is assigned via Spatie
            $hasRole = method_exists($model, 'hasRole')
                ? $model->hasRole($role->name)
                : false;

            // Get feature status from batch results
            $featureActive = null;
            if ($role->feature !== null) {
                $featureActive = $featureStatuses[$role->feature] ?? null;
            }

            return $role->withStatus($hasRole, $featureActive);
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
            // Fall back to Spatie's direct check
            return method_exists($model, 'hasRole') && $model->hasRole($role);
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
     * Clear the cached roles.
     */
    public function clearCache(): void
    {
        $this->cachedRoles = null;
        $this->featureMap = null;
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

        if (empty($features)) {
            return [];
        }

        // Use Pennant's batch checking capability
        $results = [];
        foreach ($features as $feature) {
            $results[$feature] = Feature::for($model)->active($feature);
        }

        return $results;
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
        $rolePermissions = config('mandate.role_permissions', []);

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
                feature: $role->feature,
                permissions: $permissions,
                metadata: $role->metadata,
            );
        });
    }

    /**
     * Resolve permissions from config (can be permission classes or strings).
     *
     * @param  array<mixed>  $items
     * @return array<string>
     */
    private function resolvePermissions(array $items): array
    {
        $permissions = [];

        foreach ($items as $item) {
            if (is_string($item) && class_exists($item)) {
                // Permission class - include all public string constants
                $reflection = new ReflectionClass($item);
                $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

                foreach ($constants as $const) {
                    $value = $const->getValue();
                    if (is_string($value)) {
                        $permissions[] = $value;
                    }
                }
            } elseif (is_string($item)) {
                // String permission name
                $permissions[] = $item;
            }
        }

        return $permissions;
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
