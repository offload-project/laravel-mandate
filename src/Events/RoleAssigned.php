<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when role(s) are assigned to a subject.
 */
final class RoleAssigned
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string>  $roles  The role names that were assigned
     */
    public function __construct(
        public readonly Model $subject,
        public readonly array $roles
    ) {}
}
