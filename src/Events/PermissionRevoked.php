<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when permission(s) are revoked from a subject.
 */
final class PermissionRevoked
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string>  $permissions  The permission names that were revoked
     */
    public function __construct(
        public readonly Model $subject,
        public readonly array $permissions
    ) {}
}
