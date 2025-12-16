<?php

declare(strict_types=1);

use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;

describe('PermissionsSynced Event', function () {
    it('stores sync statistics', function () {
        $event = new PermissionsSynced(
            created: 5,
            existing: 10,
            updated: 2,
            guard: 'web',
        );

        expect($event->created)->toBe(5);
        expect($event->existing)->toBe(10);
        expect($event->updated)->toBe(2);
        expect($event->guard)->toBe('web');
    });

    it('converts to array', function () {
        $event = new PermissionsSynced(
            created: 3,
            existing: 7,
            updated: 1,
        );

        expect($event->toArray())->toBe([
            'created' => 3,
            'existing' => 7,
            'updated' => 1,
        ]);
    });

    it('allows null guard', function () {
        $event = new PermissionsSynced(
            created: 0,
            existing: 0,
            updated: 0,
        );

        expect($event->guard)->toBeNull();
    });
});

describe('RolesSynced Event', function () {
    it('stores sync statistics including permissions synced', function () {
        $event = new RolesSynced(
            created: 3,
            existing: 5,
            updated: 1,
            permissionsSynced: 15,
            guard: 'api',
            seeded: true,
        );

        expect($event->created)->toBe(3);
        expect($event->existing)->toBe(5);
        expect($event->updated)->toBe(1);
        expect($event->permissionsSynced)->toBe(15);
        expect($event->guard)->toBe('api');
        expect($event->seeded)->toBeTrue();
    });

    it('converts to array', function () {
        $event = new RolesSynced(
            created: 2,
            existing: 4,
            updated: 0,
            permissionsSynced: 8,
        );

        expect($event->toArray())->toBe([
            'created' => 2,
            'existing' => 4,
            'updated' => 0,
            'permissions_synced' => 8,
        ]);
    });

    it('defaults seeded to false', function () {
        $event = new RolesSynced(
            created: 0,
            existing: 0,
            updated: 0,
            permissionsSynced: 0,
        );

        expect($event->seeded)->toBeFalse();
    });
});

describe('MandateSynced Event', function () {
    it('stores combined sync statistics', function () {
        $permissions = ['created' => 5, 'existing' => 10, 'updated' => 2];
        $roles = ['created' => 3, 'existing' => 5, 'updated' => 1, 'permissions_synced' => 15];

        $event = new MandateSynced(
            permissions: $permissions,
            roles: $roles,
            guard: 'web',
            seeded: true,
        );

        expect($event->permissions)->toBe($permissions);
        expect($event->roles)->toBe($roles);
        expect($event->guard)->toBe('web');
        expect($event->seeded)->toBeTrue();
    });

    it('defaults guard to null and seeded to false', function () {
        $event = new MandateSynced(
            permissions: ['created' => 0, 'existing' => 0, 'updated' => 0],
            roles: ['created' => 0, 'existing' => 0, 'updated' => 0, 'permissions_synced' => 0],
        );

        expect($event->guard)->toBeNull();
        expect($event->seeded)->toBeFalse();
    });
});
