<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionProperty;

/**
 * Trait for caching registry data with configurable TTL.
 *
 * @template TData of object
 */
trait CachesRegistryData
{
    /** @var Collection<int, TData>|null */
    private ?Collection $cached = null;

    /**
     * Get the cache key for this registry.
     */
    abstract protected function getCacheKey(): string;

    /**
     * Discover and build the data collection.
     *
     * @return Collection<int, TData>
     */
    abstract protected function discover(): Collection;

    /**
     * Hydrate a single item from cached array data.
     *
     * @param  array<string, mixed>  $item
     * @return TData
     */
    abstract protected function hydrateItem(array $item): object;

    /**
     * Get all items, using cache when available.
     *
     * @return Collection<int, TData>
     */
    protected function getCachedData(): Collection
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $ttl = $this->getCacheTtl();

        if ($ttl > 0) {
            /** @var array<int, array<string, mixed>> $cachedArray */
            $cachedArray = Cache::remember(
                $this->getCacheKey(),
                $ttl,
                fn () => $this->serializeForCache($this->discover())
            );

            $this->cached = collect($cachedArray)->map(
                fn (array $item) => $this->hydrateItem($item)
            );
        } else {
            $this->cached = $this->discover();
        }

        return $this->cached;
    }

    /**
     * Serialize collection for caching.
     *
     * Converts each item to an array by extracting public properties directly,
     * avoiding Spatie Data's transformation pipeline.
     *
     * @param  Collection<int, TData>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function serializeForCache(Collection $items): array
    {
        return $items->map(function (object $item): array {
            return $this->objectToArray($item);
        })->all();
    }

    /**
     * Clear the cached data.
     */
    protected function clearCachedData(): void
    {
        $this->cached = null;
        Cache::forget($this->getCacheKey());
    }

    /**
     * Get the cache TTL from config.
     */
    protected function getCacheTtl(): int
    {
        return (int) config('mandate.cache.ttl', 3600);
    }

    /**
     * Convert an object to an array by extracting its public properties.
     *
     * @return array<string, mixed>
     */
    private function objectToArray(object $item): array
    {
        $reflection = new ReflectionClass($item);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $property->getValue($item);

            // Recursively handle nested objects and arrays
            $data[$name] = $this->serializeValue($value);
        }

        return $data;
    }

    /**
     * Serialize a value for caching.
     */
    private function serializeValue(mixed $value): mixed
    {
        if (is_object($value)) {
            return $this->objectToArray($value);
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->serializeValue($v), $value);
        }

        return $value;
    }
}
