<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures\Features;

use OffloadProject\Mandate\Tests\Fixtures\Permissions\DotNotationPermissions;

/**
 * Test feature for delete functionality (gates users.delete permission).
 */
final class DeleteFeature
{
    public string $name = 'delete';

    public string $label = 'Delete Feature';

    public string $description = 'Enables delete functionality';

    /**
     * Permissions gated by this feature.
     */
    public function permissions(): array
    {
        return [
            DotNotationPermissions::USERS_DELETE,
        ];
    }

    /**
     * Resolve feature for scope.
     */
    public function resolve(mixed $scope): bool
    {
        // For testing - always return false by default
        // Tests will manually activate/deactivate
        return false;
    }
}
