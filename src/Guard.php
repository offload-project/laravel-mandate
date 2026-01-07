<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OffloadProject\Mandate\Exceptions\GuardMismatchException;

/**
 * Helper class for resolving authentication guards.
 */
final class Guard
{
    /**
     * Get the default guard name from Laravel's auth configuration.
     */
    public static function getDefaultName(): string
    {
        return config('auth.defaults.guard', 'web');
    }

    /**
     * Get the guard name for a given model.
     *
     * Resolution order:
     * 1. Model's guard_name property
     * 2. First matching guard from auth config where provider model matches
     * 3. Default guard
     */
    public static function getNameForModel(Model $model): string
    {
        // Check for explicit guard_name property
        if (property_exists($model, 'guard_name') && $model->guard_name !== null) {
            return $model->guard_name;
        }

        // Check for guard_name attribute (for dynamic models)
        if (isset($model->guard_name)) {
            return $model->guard_name;
        }

        // Try to find a matching guard from auth config
        $guardName = self::findGuardForModel($model);

        if ($guardName !== null) {
            return $guardName;
        }

        // Fall back to default guard
        return self::getDefaultName();
    }

    /**
     * Get all guard names configured in the application.
     *
     * @return Collection<int, string>
     */
    public static function getAllNames(): Collection
    {
        /** @var array<string, mixed> $guards */
        $guards = config('auth.guards', []);

        return collect(array_keys($guards));
    }

    /**
     * Validate that a guard exists.
     */
    public static function exists(string $guard): bool
    {
        return self::getAllNames()->contains($guard);
    }

    /**
     * Assert that two guards match.
     *
     * @throws GuardMismatchException
     */
    public static function assertMatch(string $expected, string $actual, string $context = 'permission'): void
    {
        if ($expected !== $actual) {
            match ($context) {
                'permission' => throw GuardMismatchException::forPermission($expected, $actual),
                'role' => throw GuardMismatchException::forRole($expected, $actual),
                default => throw GuardMismatchException::forPermission($expected, $actual),
            };
        }
    }

    /**
     * Get the model class for a guard.
     */
    public static function getModelClassForGuard(string $guard): ?string
    {
        $guards = config('auth.guards', []);
        $guardConfig = $guards[$guard] ?? null;

        if ($guardConfig === null) {
            return null;
        }

        $providerName = $guardConfig['provider'] ?? null;

        if ($providerName === null) {
            return null;
        }

        $providers = config('auth.providers', []);
        $providerConfig = $providers[$providerName] ?? null;

        return $providerConfig['model'] ?? null;
    }

    /**
     * Find a guard name where the provider's model matches the given model.
     */
    private static function findGuardForModel(Model $model): ?string
    {
        $modelClass = $model::class;
        $guards = config('auth.guards', []);
        $providers = config('auth.providers', []);

        foreach ($guards as $guardName => $guardConfig) {
            $providerName = $guardConfig['provider'] ?? null;

            if ($providerName === null) {
                continue;
            }

            $providerConfig = $providers[$providerName] ?? null;

            if ($providerConfig === null) {
                continue;
            }

            $providerModel = $providerConfig['model'] ?? null;

            if ($providerModel !== null && is_a($modelClass, $providerModel, true)) {
                return $guardName;
            }
        }

        return null;
    }
}
