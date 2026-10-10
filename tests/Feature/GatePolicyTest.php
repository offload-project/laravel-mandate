<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Tests\Fixtures\Team;
use OffloadProject\Mandate\Tests\Fixtures\TeamPolicy;
use OffloadProject\Mandate\Tests\Fixtures\User;

/*
 * A held permission used to end every Gate check that named it, so a record's
 * policy never ran: `can('team:edit', $lockedTeam)` passed whatever the policy
 * said. Now the policy decides when the check names a record it has a method
 * for, and the permission decides everything else, as before.
 */

beforeEach(function () {
    Gate::policy(Team::class, TeamPolicy::class);

    Permission::create(['name' => 'team:edit', 'guard' => 'web']);
    Permission::create(['name' => 'team:update_status', 'guard' => 'web']);
    Permission::create(['name' => 'team:archive', 'guard' => 'web']);

    $this->user = User::create(['name' => 'Holder', 'email' => 'holder@example.com']);
    $this->user->grantPermissions(['team:edit', 'team:update_status', 'team:archive']);

    $this->open = Team::create(['name' => 'Open']);
    $this->locked = Team::create(['name' => 'Locked']);
});

it('lets the policy refuse a record the permission would have allowed', function () {
    expect($this->user->can('team:edit', $this->open))->toBeTrue()
        ->and($this->user->can('team:edit', $this->locked))->toBeFalse();
});

/**
 * `#[Authorize('team:edit', [Team::class, 'team'])]` checks the class first and
 * the bound record second — the shape that slipped past the first version of
 * this fix.
 */
it('lets the policy decide when the class comes before the record', function () {
    expect($this->user->can('team:edit', [Team::class, $this->locked]))->toBeFalse()
        ->and($this->user->can('team:edit', [Team::class, $this->open]))->toBeTrue();
});

it('maps a snake-case action to its camel-case policy method', function () {
    expect($this->user->can('team:update_status', $this->locked))->toBeFalse()
        ->and($this->user->can('team:update_status', $this->open))->toBeTrue();
});

it('still refuses someone without the permission', function () {
    $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);

    expect($other->can('team:edit', $this->open))->toBeFalse();
});

it('decides by the permission when the check names no record', function () {
    expect($this->user->can('team:edit'))->toBeTrue()
        ->and($this->user->can('team:edit', Team::class))->toBeTrue();
});

it('decides by the permission when the policy has no method for the action', function () {
    expect($this->user->can('team:archive', $this->locked))->toBeTrue();
});

it('decides by the permission when the record has no policy', function () {
    $unpoliced = $this->user;

    expect($this->user->can('team:edit', $unpoliced))->toBeTrue();
});

it('can be turned off, granting on the permission alone as before', function () {
    config(['mandate.gate.defer_to_policies' => false]);

    expect($this->user->can('team:edit', $this->locked))->toBeTrue();
});
