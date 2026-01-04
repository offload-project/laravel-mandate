<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Hoist\Services\FeatureDiscovery;
use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Discovers features via Hoist and extracts their permission/role mappings.
 */
final class FeatureRegistry implements FeatureRegistryContract
{
    /** @var Collection<int, FeatureData>|null */
    private ?Collection $cachedFeatures = null;

    public function __construct(
        private readonly FeatureDiscovery $discovery,
    ) {}

    /**
     * Get all discovered features with their permissions and roles.
     *
     * @return Collection<int, FeatureData>
     */
    public function all(): Collection
    {
        if ($this->cachedFeatures !== null) {
            return $this->cachedFeatures;
        }

        $features = collect();

        // Discover features via Hoist
        foreach ($this->discovery->discover() as $featureClass) {
            $feature = $this->buildFeatureData($featureClass);
            if ($feature !== null) {
                $features->push($feature);
            }
        }

        $this->cachedFeatures = $features;

        return $this->cachedFeatures;
    }

    /**
     * Get features with active status for a specific model.
     *
     * @return Collection<int, FeatureData>
     */
    public function forModel(Model $model): Collection
    {
        return $this->all()->map(function (FeatureData $feature) use ($model) {
            $active = $this->isActive($model, $feature->class);

            return new FeatureData(
                class: $feature->class,
                name: $feature->name,
                label: $feature->label,
                description: $feature->description,
                active: $active,
                permissions: $feature->permissions,
                roles: $feature->roles,
                metadata: $feature->metadata,
            );
        });
    }

    /**
     * Get a specific feature by class name.
     */
    public function find(string $class): ?FeatureData
    {
        return $this->all()->firstWhere('class', $class);
    }

    /**
     * Get permissions for a specific feature.
     *
     * @return Collection<int, PermissionData>
     */
    public function permissions(string $class): Collection
    {
        $feature = $this->find($class);

        return collect($feature->permissions ?? []);
    }

    /**
     * Get roles for a specific feature.
     *
     * @return Collection<int, RoleData>
     */
    public function roles(string $class): Collection
    {
        $feature = $this->find($class);

        return collect($feature->roles ?? []);
    }

    /**
     * Check if a feature is active for a model.
     */
    public function isActive(Model $model, string $class): bool
    {
        // Check if Pennant is available
        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return false;
        }

        return \Laravel\Pennant\Feature::for($model)->active($class);
    }

    /**
     * Clear the cached features.
     */
    public function clearCache(): void
    {
        $this->cachedFeatures = null;
    }

    /**
     * Build FeatureData from a feature class.
     */
    private function buildFeatureData(string $featureClass): ?FeatureData
    {
        if (! class_exists($featureClass)) {
            return null;
        }

        $instance = new $featureClass;

        // Extract permissions from the feature
        $permissions = $this->extractPermissions($instance);

        // Extract roles from the feature
        $roles = $this->extractRoles($instance);

        return FeatureData::fromFeature($instance, $permissions, $roles);
    }

    /**
     * Extract permissions defined on a feature.
     *
     * @return array<PermissionData>
     */
    private function extractPermissions(object $feature): array
    {
        if (! method_exists($feature, 'permissions')) {
            return [];
        }

        $permissions = [];
        $defined = $feature->permissions();

        foreach ($defined as $item) {
            if (is_string($item) && class_exists($item)) {
                // Check if it's a permission class
                $reflection = new ReflectionClass($item);
                if (! empty($reflection->getAttributes(PermissionsSet::class))) {
                    $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);
                    foreach ($constants as $const) {
                        if (is_string($const->getValue())) {
                            $permissions[] = PermissionData::fromClassConstant($item, $const->getName(), $feature::class);
                        }
                    }
                }
            } elseif (is_string($item)) {
                // String permission name
                $permissions[] = PermissionData::simple($item);
            }
        }

        return $permissions;
    }

    /**
     * Extract roles defined on a feature.
     *
     * @return array<RoleData>
     */
    private function extractRoles(object $feature): array
    {
        if (! method_exists($feature, 'roles')) {
            return [];
        }

        $roles = [];
        $defined = $feature->roles();

        foreach ($defined as $item) {
            if (is_string($item) && class_exists($item)) {
                // Check if it's a role class
                $reflection = new ReflectionClass($item);
                if (! empty($reflection->getAttributes(RoleSet::class))) {
                    $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);
                    foreach ($constants as $const) {
                        if (is_string($const->getValue())) {
                            $roles[] = RoleData::fromClassConstant($item, $const->getName(), $feature::class);
                        }
                    }
                }
            }
        }

        return $roles;
    }
}
