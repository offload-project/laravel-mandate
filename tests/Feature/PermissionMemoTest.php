<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

/*
 * Every check used to reach the database on its own — direct permissions, then
 * roles, then capabilities, each its own `exists` query. A page gating fifteen
 * navigation items spent sixty queries asking the same few questions.
 *
 * The memo is per instance, so it cannot outlive the request that built the
 * model, and anything that changes the answer clears it.
 */

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'memo@example.com']);
});

function queriesWhile(Closure $work): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $work();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}

it('asks the database once for the same question', function () {
    $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);
    $this->user->grantPermission($permission);

    $first = queriesWhile(fn () => $this->user->hasPermission('edit'));
    $repeat = queriesWhile(function () {
        for ($i = 0; $i < 10; $i++) {
            $this->user->hasPermission('edit');
        }
    });

    expect($first)->toBeGreaterThan(0)
        ->and($repeat)->toBe(0);
});

it('keeps a denial from becoming a yes, and the answers apart', function () {
    Permission::create(['name' => 'edit', 'guard' => 'web']);
    Permission::create(['name' => 'delete', 'guard' => 'web']);

    expect($this->user->hasPermission('edit'))->toBeFalse();

    $this->user->grantPermission('delete');

    // A memo keyed too loosely would answer 'edit' with what it learned about
    // 'delete', or keep answering the stale denial.
    expect($this->user->hasPermission('delete'))->toBeTrue()
        ->and($this->user->hasPermission('edit'))->toBeFalse();
});

it('forgets what it knew when a permission is granted or revoked', function () {
    $permission = Permission::create(['name' => 'edit', 'guard' => 'web']);

    expect($this->user->hasPermission('edit'))->toBeFalse();

    $this->user->grantPermission($permission);
    expect($this->user->hasPermission('edit'))->toBeTrue();

    $this->user->revokePermission($permission);
    expect($this->user->hasPermission('edit'))->toBeFalse();
});

it('forgets what it knew when a role is assigned or removed', function () {
    $permission = Permission::create(['name' => 'publish', 'guard' => 'web']);
    $role = Role::create(['name' => 'editor', 'guard' => 'web']);
    $role->grantPermission($permission);

    expect($this->user->hasPermission('publish'))->toBeFalse();

    $this->user->assignRole($role);
    expect($this->user->hasPermission('publish'))->toBeTrue();

    $this->user->removeRole($role);
    expect($this->user->hasPermission('publish'))->toBeFalse();
});

/*
 * The memo cannot see a write made through another model, which is the one
 * thing a caller has to know about it.
 */
it('can be told to forget when the change happened elsewhere', function () {
    $permission = Permission::create(['name' => 'archive', 'guard' => 'web']);
    $role = Role::create(['name' => 'editor', 'guard' => 'web']);
    $this->user->assignRole($role);

    expect($this->user->hasPermission('archive'))->toBeFalse();

    $role->grantPermission($permission);

    expect($this->user->forgetMandateAnswers()->hasPermission('archive'))->toBeTrue();
});
