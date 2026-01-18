<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

/**
 * Result of a sync operation.
 */
final readonly class SyncResult
{
    public function __construct(
        public int $permissionsCreated = 0,
        public int $permissionsUpdated = 0,
        public int $rolesCreated = 0,
        public int $rolesUpdated = 0,
        public int $capabilitiesCreated = 0,
        public int $capabilitiesUpdated = 0,
        public bool $assignmentsSeeded = false,
    ) {}

    /**
     * Get the total number of items created.
     */
    public function totalCreated(): int
    {
        return $this->permissionsCreated + $this->rolesCreated + $this->capabilitiesCreated;
    }

    /**
     * Get the total number of items updated.
     */
    public function totalUpdated(): int
    {
        return $this->permissionsUpdated + $this->rolesUpdated + $this->capabilitiesUpdated;
    }

    /**
     * Get the total number of items synced (created + updated).
     */
    public function total(): int
    {
        return $this->totalCreated() + $this->totalUpdated();
    }

    /**
     * Check if any changes were made.
     */
    public function hasChanges(): bool
    {
        return $this->total() > 0 || $this->assignmentsSeeded;
    }
}
