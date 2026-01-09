<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Event dispatched when roles are synced from code-first definitions.
 */
final class RolesSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  int  $created  Number of roles created
     * @param  int  $updated  Number of roles updated
     * @param  Collection<int, string>  $roles  Role names that were synced
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly Collection $roles
    ) {}
}
