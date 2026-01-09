<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use OffloadProject\Mandate\Events\CapabilityAssigned;
use OffloadProject\Mandate\Events\CapabilityRemoved;
use OffloadProject\Mandate\Events\PermissionGranted;
use OffloadProject\Mandate\Events\PermissionRevoked;
use OffloadProject\Mandate\Events\RoleAssigned;
use OffloadProject\Mandate\Events\RoleRemoved;
use OffloadProject\Mandate\Models\Capability;
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

    it('CapabilityAssigned event has correct properties', function () {
        $event = new CapabilityAssigned($this->user, ['manage-posts', 'manage-users']);

        expect($event->subject)->toBe($this->user)
            ->and($event->capabilities)->toBe(['manage-posts', 'manage-users']);
    });

    it('CapabilityRemoved event has correct properties', function () {
        $event = new CapabilityRemoved($this->user, ['manage-posts']);

        expect($event->subject)->toBe($this->user)
            ->and($event->capabilities)->toBe(['manage-posts']);
    });
});

describe('Capability Events on Role', function () {
    beforeEach(function () {
        $this->enableCapabilities();
    });

    it('fires CapabilityAssigned event when assigning capability to role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $role->assignCapability('manage-posts');

        Event::assertDispatched(CapabilityAssigned::class, function ($event) use ($role) {
            return $event->subject->id === $role->id
                && in_array('manage-posts', $event->capabilities);
        });
    });

    it('fires CapabilityAssigned event with multiple capabilities', function () {
        $role = Role::create(['name' => 'admin', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);

        $role->assignCapability(['manage-posts', 'manage-users']);

        Event::assertDispatched(CapabilityAssigned::class, function ($event) {
            return count($event->capabilities) === 2
                && in_array('manage-posts', $event->capabilities)
                && in_array('manage-users', $event->capabilities);
        });
    });

    it('fires CapabilityRemoved event when removing capability from role', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        config(['mandate.events' => false]);
        $role->assignCapability('manage-posts');
        config(['mandate.events' => true]);

        $role->removeCapability('manage-posts');

        Event::assertDispatched(CapabilityRemoved::class, function ($event) use ($role) {
            return $event->subject->id === $role->id
                && in_array('manage-posts', $event->capabilities);
        });
    });

    it('does not fire capability events when events disabled', function () {
        config(['mandate.events' => false]);
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $role->assignCapability('manage-posts');

        Event::assertNotDispatched(CapabilityAssigned::class);
    });
});

describe('Capability Events on Subject', function () {
    beforeEach(function () {
        $this->enableCapabilities();
        $this->enableDirectCapabilityAssignment();
    });

    it('fires CapabilityAssigned event when assigning capability to user', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->user->assignCapability('manage-posts');

        Event::assertDispatched(CapabilityAssigned::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('manage-posts', $event->capabilities);
        });
    });

    it('fires CapabilityRemoved event when removing capability from user', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        config(['mandate.events' => false]);
        $this->user->assignCapability('manage-posts');
        config(['mandate.events' => true]);

        $this->user->removeCapability('manage-posts');

        Event::assertDispatched(CapabilityRemoved::class, function ($event) {
            return $event->subject->id === $this->user->id
                && in_array('manage-posts', $event->capabilities);
        });
    });
});
