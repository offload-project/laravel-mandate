<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Tests\Fixtures;

/**
 * A policy with a rule beyond the permission: a locked team cannot be edited by
 * anyone, whatever they hold.
 */
class TeamPolicy
{
    public function edit(User $user, Team $team): bool
    {
        return $team->name !== 'Locked';
    }

    public function updateStatus(User $user, Team $team): bool
    {
        return $team->name !== 'Locked';
    }
}
