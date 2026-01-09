<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when permission(s) are granted to a subject.
 */
final class PermissionGranted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string>  $permissions  The permission names that were granted
     */
    public function __construct(
        public readonly Model $subject,
        public readonly array $permissions
    ) {}
}
