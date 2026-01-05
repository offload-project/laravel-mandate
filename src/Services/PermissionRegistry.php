<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Attributes\PermissionsSet;
use OffloadProject\Mandate\Concerns\DiscoversClasses;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Support\WildcardMatcher;
use ReflectionClass;
use ReflectionClassConstant;

/**
 * Discovers and manages permissions from class constants and configuration.
 */
final class PermissionRegistry implements PermissionRegistryContract
{
    use DiscoversClasses;

    public const CACHE_KEY = 'mandate.permissions';

    /** @var Collection<int, PermissionData>|null */
    private ?Collection $cachedPermissions = null;

    /** @var array<string, string>|null Map of permission name to feature class */
    private ?array $featureMap = null;

    public function __construct(
        private readonly FeatureRegistryContract $featureRegistry,
    ) {}

    /**
     * Get all discovered permissions.
     *
     * @return Collection<int, PermissionData>
     */
    public function all(): Collection
    {
        if ($this->cachedPermissions !== null) {
            return $this->cachedPermissions;
        }

        $ttl = config('mandate.cache.ttl', 3600);

        if ($ttl > 0) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->discover()->toArray());
            $this->cachedPermissions = collect($cached)->map(fn (array $item) => new PermissionData(...$item));
        } else {
            $this->cachedPermissions = $this->discover();
        }

        return $this->cachedPermissions;
    }

    /**
     * Get permissions with active status for a specific model.
     *
     * Uses batch feature flag checking for improved performance.
     *
     * @return Collection<int, PermissionData>
     */
    public function forModel(Model $model): Collection
    {
        $permissions = $this->all();

        // Batch check all unique features for better performance
        $featureStatuses = $this->batchCheckFeatures($model, $permissions);

        return $permissions->map(function (PermissionData $permission) use ($model, $featureStatuses) {
            // Check if permission is assigned via our traits
            $isGranted = method_exists($model, 'granted')
                ? $model->granted($permission->name)
                : false;

            // Get feature status from batch results
            $featureActive = null;
            if ($permission->feature !== null) {
                $featureActive = $featureStatuses[$permission->feature] ?? null;
            }

            return $permission->withStatus($isGranted, $featureActive);
        });
    }

    /**
     * Get granted permissions for a model (has permission AND feature is active).
     *
     * @return Collection<int, PermissionData>
     */
    public function granted(Model $model): Collection
    {
        return $this->forModel($model)->filter(fn (PermissionData $p) => $p->isGranted());
    }

    /**
     * Get available permissions (not gated by feature or feature is active).
     *
     * @return Collection<int, PermissionData>
     */
    public function available(Model $model): Collection
    {
        return $this->forModel($model)->filter(fn (PermissionData $p) => $p->isAvailable());
    }

    /**
     * Check if a model has a specific permission (considering feature flags).
     *
     * Supports wildcard patterns:
     *   - 'users.*' matches any permission starting with 'users.'
     *   - '*.view' matches any permission ending with '.view'
     */
    public function can(Model $model, string $permission): bool
    {
        // Check if this is a wildcard pattern
        if (WildcardMatcher::isWildcard($permission)) {
            return $this->canWithWildcard($model, $permission);
        }

        $permissionData = $this->forModel($model)->firstWhere('name', $permission);

        if ($permissionData === null) {
            // Fall back to direct check for permissions not in registry
            return method_exists($model, 'granted') && $model->granted($permission);
        }

        return $permissionData->isGranted();
    }

    /**
     * Get all permission names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return $this->all()->pluck('name')->all();
    }

    /**
     * Get permissions grouped by their set name.
     *
     * @return Collection<string, Collection<int, PermissionData>>
     */
    public function grouped(): Collection
    {
        return $this->all()->groupBy(fn (PermissionData $p) => $p->set ?? 'default');
    }

    /**
     * Get a specific permission by name.
     */
    public function find(string $permission): ?PermissionData
    {
        return $this->all()->firstWhere('name', $permission);
    }

    /**
     * Get the feature class that gates a permission.
     */
    public function feature(string $permission): ?string
    {
        return $this->find($permission)?->feature;
    }

    /**
     * Clear the cached permissions.
     */
    public function clearCache(): void
    {
        $this->cachedPermissions = null;
        $this->featureMap = null;
        Cache::forget(self::CACHE_KEY);
        WildcardMatcher::clearCache();
    }

    /**
     * Discover permissions from directories and apply mappings.
     *
     * @return Collection<int, PermissionData>
     */
    private function discover(): Collection
    {
        $permissions = $this->discoverFromDirectories(
            'mandate.discovery.permissions',
            PermissionsSet::class,
            fn (string $class) => $this->extractFromClass($class)
        );

        // Apply feature mappings from features
        $permissions = $this->applyFeatureMappings($permissions);

        return $permissions->unique('name')->values();
    }

    /**
     * Check if a model has any permission matching a wildcard pattern.
     */
    private function canWithWildcard(Model $model, string $pattern): bool
    {
        foreach ($this->forModel($model) as $permissionData) {
            if (WildcardMatcher::matches($pattern, $permissionData->name)) {
                if ($permissionData->isGranted()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Batch check feature flags for all unique features.
     *
     * @param  Collection<int, PermissionData>  $permissions
     * @return array<string, bool>
     */
    private function batchCheckFeatures(Model $model, Collection $permissions): array
    {
        $features = $permissions
            ->pluck('feature')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($features)) {
            return [];
        }

        // Check if Pennant is available
        if (! class_exists(Feature::class)) {
            // Return all features as inactive when Pennant is not installed
            return array_fill_keys($features, false);
        }

        // Use Pennant's batch checking capability
        $results = [];
        foreach ($features as $feature) {
            $results[$feature] = Feature::for($model)->active($feature);
        }

        return $results;
    }

    /**
     * Extract permissions from a class with constants.
     *
     * @param  class-string  $class
     * @return Collection<int, PermissionData>
     */
    private function extractFromClass(string $class): Collection
    {
        $reflection = new ReflectionClass($class);
        $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

        return collect($constants)
            ->filter(fn (ReflectionClassConstant $const) => is_string($const->getValue()))
            ->map(fn (ReflectionClassConstant $const) => PermissionData::fromClassConstant($class, $const->getName()));
    }

    /**
     * Apply feature mappings from discovered features.
     *
     * @param  Collection<int, PermissionData>  $permissions
     * @return Collection<int, PermissionData>
     */
    private function applyFeatureMappings(Collection $permissions): Collection
    {
        $featureMap = $this->getFeatureMap();

        return $permissions->map(function (PermissionData $permission) use ($featureMap) {
            $feature = $featureMap[$permission->name] ?? null;

            if ($feature !== null && $permission->feature === null) {
                return new PermissionData(
                    name: $permission->name,
                    label: $permission->label,
                    description: $permission->description,
                    set: $permission->set,
                    guard: $permission->guard,
                    scope: $permission->scope,
                    feature: $feature,
                    metadata: $permission->metadata,
                );
            }

            return $permission;
        });
    }

    /**
     * Build the feature->permission mapping.
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
            foreach ($feature->permissions as $permission) {
                $this->featureMap[$permission->name] = $feature->class;
            }
        }

        return $this->featureMap;
    }
}
