<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands\Concerns;

use Illuminate\Support\Str;

/**
 * Provides shared functionality for resolving configured paths to namespaces.
 *
 * Used by generator commands to respect the paths defined in config('mandate.code_first.paths').
 */
trait ResolvesConfiguredPaths
{
    /**
     * Convert a filesystem path to a PSR-4 namespace.
     *
     * Handles paths both inside and outside the app directory by checking
     * the application's PSR-4 autoload configuration.
     */
    protected function pathToNamespace(string $path): string
    {
        $path = trim($path, DIRECTORY_SEPARATOR);

        // Try app directory first (most common case)
        $appPath = $this->laravel->basePath('app');

        if (str_starts_with($path, $appPath)) {
            $relativePath = mb_substr($path, mb_strlen($appPath));
            $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
            $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            return trim($this->laravel->getNamespace() . $namespace, '\\');
        }

        // For paths outside app directory, try to resolve from composer autoload
        $basePath = $this->laravel->basePath();

        if (str_starts_with($path, $basePath)) {
            $relativePath = mb_substr($path, mb_strlen($basePath));
            $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);

            // Check composer.json for PSR-4 mappings
            $namespace = $this->resolveNamespaceFromComposer($relativePath);

            if ($namespace !== null) {
                return $namespace;
            }

            // Fall back to converting path directly (e.g., src/Permissions -> Src\Permissions)
            return str_replace(DIRECTORY_SEPARATOR, '\\', Str::studly($relativePath));
        }

        // Absolute path outside project - use the path segments as namespace
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $relevantSegments = array_slice($segments, -2); // Take last 2 segments

        return implode('\\', array_map([Str::class, 'studly'], $relevantSegments));
    }

    /**
     * Attempt to resolve namespace from composer.json PSR-4 autoload config.
     */
    protected function resolveNamespaceFromComposer(string $relativePath): ?string
    {
        $composerPath = $this->laravel->basePath('composer.json');

        if (!file_exists($composerPath)) {
            return null;
        }

        $contents = file_get_contents($composerPath);

        if ($contents === false) {
            return null;
        }

        $composer = json_decode($contents, true);
        $autoload = $composer['autoload']['psr-4'] ?? [];

        foreach ($autoload as $namespace => $paths) {
            $paths = (array)$paths;

            foreach ($paths as $autoloadPath) {
                $autoloadPath = trim($autoloadPath, '/');

                if (str_starts_with($relativePath, $autoloadPath)) {
                    $remainder = mb_substr($relativePath, mb_strlen($autoloadPath));
                    $remainder = trim($remainder, DIRECTORY_SEPARATOR);
                    $namespaceSuffix = str_replace(DIRECTORY_SEPARATOR, '\\', $remainder);

                    return trim($namespace . $namespaceSuffix, '\\');
                }
            }
        }

        return null;
    }
}
