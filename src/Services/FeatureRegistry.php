<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Hoist\Services\FeatureDiscovery;
use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Attributes\RoleSet;
use OffloadProject\Mandate\Concerns\CachesRegistryData;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Support\PennantHelper;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Discovers features via Hoist and extracts their permission/role mappings.
 */
final class FeatureRegistry implements FeatureRegistryContract
{
    /** @use CachesRegistryData<FeatureData> */
    use CachesRegistryData;

    public const CACHE_KEY = 'mandate.features';

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
        return $this->getCachedData();
    }

    /**
     * Get features with active status for a specific model.
     *
     * @return Collection<int, FeatureData>
     */
    public function forModel(Model $model): Collection
    {
        $features = $this->all();

        // Batch check all features for better performance
        $featureClasses = $features->pluck('class')->all();
        $featureStatuses = PennantHelper::batchCheck($model, $featureClasses);

        return $features->map(function (FeatureData $feature) use ($featureStatuses) {
            $active = $featureStatuses[$feature->class] ?? false;

            return $feature->withActive($active);
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

        return collect($feature !== null ? $feature->permissions : []);
    }

    /**
     * Get roles for a specific feature.
     *
     * @return Collection<int, RoleData>
     */
    public function roles(string $class): Collection
    {
        $feature = $this->find($class);

        return collect($feature !== null ? $feature->roles : []);
    }

    /**
     * Check if a feature is active for a model.
     */
    public function isActive(Model $model, string $class): bool
    {
        return PennantHelper::isActive($model, $class);
    }

    /**
     * Clear the cached features.
     */
    public function clearCache(): void
    {
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
     * Hydrate a FeatureData from a cached array.
     *
     * @param  array<string, mixed>  $item
     */
    protected function hydrateItem(array $item): FeatureData
    {
        return FeatureData::fromArray($item);
    }

    /**
     * Discover features from Hoist.
     *
     * @return Collection<int, FeatureData>
     */
    protected function discover(): Collection
    {
        $features = collect();

        foreach ($this->discovery->discover() as $featureClass) {
            $feature = $this->buildFeatureData($featureClass);
            if ($feature !== null) {
                $features->push($feature);
            }
        }

        return $features;
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

        $permissions = $this->extractPermissions($instance);
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
