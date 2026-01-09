<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Event dispatched when capabilities are synced from code-first definitions.
 */
final class CapabilitiesSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  int  $created  Number of capabilities created
     * @param  int  $updated  Number of capabilities updated
     * @param  Collection<int, string>  $capabilities  Capability names that were synced
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly Collection $capabilities
    ) {}
}
