<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Features;

use OffloadProject\Mandate\Tests\Fixtures\Permissions\UserPermissions;

/**
 * Test feature for export functionality.
 */
final class ExportFeature
{
    public string $name = 'export';

    public string $label = 'Export Feature';

    public string $description = 'Enables data export functionality';

    /**
     * Permissions gated by this feature.
     */
    public function permissions(): array
    {
        return [
            UserPermissions::EXPORT,
        ];
    }

    /**
     * Resolve feature for scope.
     */
    public function resolve(mixed $scope): bool
    {
        // For testing - enable for specific users
        if (is_object($scope) && property_exists($scope, 'id')) {
            return $scope->id === 1;
        }

        return false;
    }
}
