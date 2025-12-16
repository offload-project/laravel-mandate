<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched after both permissions and roles have been synced.
 */
final class MandateSynced
{
    use Dispatchable;

    /**
     * @param  array{created: int, existing: int, updated: int}  $permissions  Permission sync statistics
     * @param  array{created: int, existing: int, updated: int, permissions_synced: int}  $roles  Role sync statistics
     * @param  string|null  $guard  The guard that was synced
     * @param  bool  $seeded  Whether role-permission relationships were seeded
     */
    public function __construct(
        public readonly array $permissions,
        public readonly array $roles,
        public readonly ?string $guard = null,
        public readonly bool $seeded = false,
    ) {}
}
