<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched after roles have been synced to the database.
 */
final class RolesSynced
{
    use Dispatchable;

    /**
     * @param  int  $created  Number of roles created
     * @param  int  $existing  Number of existing roles found
     * @param  int  $updated  Number of roles updated
     * @param  int  $permissionsSynced  Number of permissions synced to roles
     * @param  string|null  $guard  The guard that was synced
     * @param  bool  $seeded  Whether role-permission relationships were seeded
     */
    public function __construct(
        public readonly int $created,
        public readonly int $existing,
        public readonly int $updated,
        public readonly int $permissionsSynced,
        public readonly ?string $guard = null,
        public readonly bool $seeded = false,
    ) {}

    /**
     * Get the sync statistics as an array.
     *
     * @return array{created: int, existing: int, updated: int, permissions_synced: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'existing' => $this->existing,
            'updated' => $this->updated,
            'permissions_synced' => $this->permissionsSynced,
        ];
    }
}
