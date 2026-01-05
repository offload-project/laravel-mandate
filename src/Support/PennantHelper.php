<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Support;

use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

/**
 * Helper class for Laravel Pennant feature flag operations.
 *
 * Centralizes Pennant availability checks and provides a consistent
 * interface for feature flag operations throughout the package.
 */
final class PennantHelper
{
    private static ?bool $available = null;

    /**
     * Check if Laravel Pennant is available.
     */
    public static function available(): bool
    {
        if (self::$available === null) {
            self::$available = class_exists(Feature::class);
        }

        return self::$available;
    }

    /**
     * Check if a feature is active for a scope.
     */
    public static function isActive(Model|string $scope, string $feature): bool
    {
        if (! self::available()) {
            return false;
        }

        return Feature::for($scope)->active($feature);
    }

    /**
     * Batch check multiple features for a scope.
     *
     * Returns an associative array of feature => bool status.
     *
     * @param  array<string>  $features
     * @return array<string, bool>
     */
    public static function batchCheck(Model|string $scope, array $features): array
    {
        if (empty($features)) {
            return [];
        }

        if (! self::available()) {
            return array_fill_keys($features, false);
        }

        // Use Pennant's values() for true batch checking
        $values = Feature::for($scope)->values($features);

        // Convert to boolean array (values() returns mixed values)
        $results = [];
        foreach ($features as $feature) {
            $results[$feature] = (bool) ($values[$feature] ?? false);
        }

        return $results;
    }

    /**
     * Activate a feature for a scope.
     */
    public static function activate(Model|string $scope, string $feature): void
    {
        if (! self::available()) {
            return;
        }

        Feature::for($scope)->activate($feature);
    }

    /**
     * Deactivate a feature for a scope.
     */
    public static function deactivate(Model|string $scope, string $feature): void
    {
        if (! self::available()) {
            return;
        }

        Feature::for($scope)->deactivate($feature);
    }

    /**
     * Activate a feature for everyone.
     */
    public static function activateForEveryone(string $feature): void
    {
        if (! self::available()) {
            return;
        }

        Feature::activateForEveryone($feature);
    }

    /**
     * Deactivate a feature for everyone.
     */
    public static function deactivateForEveryone(string $feature): void
    {
        if (! self::available()) {
            return;
        }

        Feature::deactivateForEveryone($feature);
    }

    /**
     * Purge stored feature values.
     *
     * @param  string|array<string>  $features
     */
    public static function purge(string|array $features): void
    {
        if (! self::available()) {
            return;
        }

        Feature::purge($features);
    }

    /**
     * Forget feature value for a scope.
     */
    public static function forget(Model|string $scope, string $feature): void
    {
        if (! self::available()) {
            return;
        }

        Feature::for($scope)->forget($feature);
    }

    /**
     * Reset the availability cache (useful for testing).
     */
    public static function resetCache(): void
    {
        self::$available = null;
    }
}
