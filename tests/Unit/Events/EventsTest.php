<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use OffloadProject\Mandate\Events\PermissionGranted;
use OffloadProject\Mandate\Events\PermissionRevoked;
use OffloadProject\Mandate\Events\RoleAssigned;
use OffloadProject\Mandate\Events\RoleRemoved;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->enableEvents();
    Event::fake();
});

describe('Role Events', function () {
    it('fires RoleAssigned event when assigning role', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->user->assignRole('admin');

        Event::assertDispatched(RoleAssigned::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('admin', $event->roles);
        });
    });

    it('fires RoleAssigned event with multiple roles', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);

        $this->user->assignRoles(['admin', 'editor']);

        Event::assertDispatched(RoleAssigned::class, function ($event) {
            return count($event->roles) === 2
                && in_array('admin', $event->roles)
                && in_array('editor', $event->roles);
        });
    });

    it('fires RoleRemoved event when removing role', function () {
        Role::create(['name' => 'admin', 'guard' => 'web']);
        config(['mandate.events' => false]);
        $this->user->assignRole('admin');
        config(['mandate.events' => true]);

        $this->user->removeRole('admin');

        Event::assertDispatched(RoleRemoved::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('admin', $event->roles);
        });
    });

    it('does not fire events when disabled', function () {
        config(['mandate.events' => false]);
        Role::create(['name' => 'admin', 'guard' => 'web']);

        $this->user->assignRole('admin');

        Event::assertNotDispatched(RoleAssigned::class);
    });
});

describe('Permission Events', function () {
    it('fires PermissionGranted event when granting permission', function () {
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $this->user->grantPermission('article:edit');

        Event::assertDispatched(PermissionGranted::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('article:edit', $event->permissions);
        });
    });

    it('fires PermissionGranted event with multiple permissions', function () {
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        Permission::create(['name' => 'article:delete', 'guard' => 'web']);

        $this->user->grantPermissions(['article:edit', 'article:delete']);

        Event::assertDispatched(PermissionGranted::class, function ($event) {
            return count($event->permissions) === 2
                && in_array('article:edit', $event->permissions)
                && in_array('article:delete', $event->permissions);
        });
    });

    it('fires PermissionRevoked event when revoking permission', function () {
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        config(['mandate.events' => false]);
        $this->user->grantPermission('article:edit');
        config(['mandate.events' => true]);

        $this->user->revokePermission('article:edit');

        Event::assertDispatched(PermissionRevoked::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('article:edit', $event->permissions);
        });
    });

    it('does not fire events when disabled', function () {
        config(['mandate.events' => false]);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        $this->user->grantPermission('article:edit');

        Event::assertNotDispatched(PermissionGranted::class);
    });
});

describe('Event Properties', function () {
    it('RoleAssigned event has correct properties', function () {
        $event = new RoleAssigned($this->user, ['admin', 'editor']);

        expect($event->subject)->toBe($this->user)
            ->and($event->roles)->toBe(['admin', 'editor']);
    });

    it('RoleRemoved event has correct properties', function () {
        $event = new RoleRemoved($this->user, ['admin']);

        expect($event->subject)->toBe($this->user)
            ->and($event->roles)->toBe(['admin']);
    });

    it('PermissionGranted event has correct properties', function () {
        $event = new PermissionGranted($this->user, ['article:edit', 'article:delete']);

        expect($event->subject)->toBe($this->user)
            ->and($event->permissions)->toBe(['article:edit', 'article:delete']);
    });

    it('PermissionRevoked event has correct properties', function () {
        $event = new PermissionRevoked($this->user, ['article:edit']);

        expect($event->subject)->toBe($this->user)
            ->and($event->permissions)->toBe(['article:edit']);
    });
});
