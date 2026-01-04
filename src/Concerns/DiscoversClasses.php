<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;

/**
 * Trait for discovering classes from configured directories.
 */
trait DiscoversClasses
{
    /**
     * Discover classes from configured directories that have a specific attribute.
     *
     * @param  string  $configKey  The config key for directories (e.g., 'mandate.discovery.permissions')
     * @param  class-string  $attributeClass  The attribute class that marks valid classes
     * @param  callable(class-string): Collection<int, mixed>  $extractor  Function to extract data from each class
     * @return Collection<int, mixed>
     */
    protected function discoverFromDirectories(string $configKey, string $attributeClass, callable $extractor): Collection
    {
        $directories = config($configKey, []);
        $items = collect();

        foreach ($directories as $directory => $namespace) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $class = $this->resolveClassName($file->getPathname(), $directory, $namespace);

                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if (empty($reflection->getAttributes($attributeClass))) {
                    continue;
                }

                /** @var class-string $class */
                $items = $items->merge($extractor($class));
            }
        }

        return $items;
    }

    /**
     * Resolve a fully qualified class name from a file path.
     */
    protected function resolveClassName(string $filePath, string $directory, string $namespace): string
    {
        $relativePath = str_replace($directory, '', $filePath);
        $relativePath = mb_ltrim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        return $namespace.'\\'.$relativePath;
    }
}
