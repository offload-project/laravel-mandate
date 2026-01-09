<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a full Mandate sync completes.
 */
final class MandateSynced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PermissionsSynced $permissions,
        public readonly RolesSynced $roles,
        public readonly ?CapabilitiesSynced $capabilities = null
    ) {}

    /**
     * Get the total number of items created.
     */
    public function totalCreated(): int
    {
        $total = $this->permissions->created + $this->roles->created;

        if ($this->capabilities !== null) {
            $total += $this->capabilities->created;
        }

        return $total;
    }

    /**
     * Get the total number of items updated.
     */
    public function totalUpdated(): int
    {
        $total = $this->permissions->updated + $this->roles->updated;

        if ($this->capabilities !== null) {
            $total += $this->capabilities->updated;
        }

        return $total;
    }
}
