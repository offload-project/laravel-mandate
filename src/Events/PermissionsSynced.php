<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched after permissions have been synced to the database.
 */
final class PermissionsSynced
{
    use Dispatchable;

    /**
     * @param  int  $created  Number of permissions created
     * @param  int  $existing  Number of existing permissions found
     * @param  int  $updated  Number of permissions updated
     * @param  string|null  $guard  The guard that was synced
     */
    public function __construct(
        public readonly int $created,
        public readonly int $existing,
        public readonly int $updated,
        public readonly ?string $guard = null,
    ) {}

    /**
     * Get the sync statistics as an array.
     *
     * @return array{created: int, existing: int, updated: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'existing' => $this->existing,
            'updated' => $this->updated,
        ];
    }
}
