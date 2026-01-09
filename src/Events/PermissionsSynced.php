<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Event dispatched when permissions are synced from code-first definitions.
 */
final class PermissionsSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  int  $created  Number of permissions created
     * @param  int  $updated  Number of permissions updated
     * @param  Collection<int, string>  $permissions  Permission names that were synced
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly Collection $permissions
    ) {}
}
