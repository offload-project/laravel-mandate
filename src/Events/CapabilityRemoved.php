<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when capability(s) are removed from a subject or role.
 */
final class CapabilityRemoved
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string>  $capabilities  The capability names that were removed
     */
    public function __construct(
        public readonly Model $subject,
        public readonly array $capabilities
    ) {}
}
