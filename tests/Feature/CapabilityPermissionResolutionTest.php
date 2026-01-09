<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->enableCapabilities();
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
});

describe('Permission Resolution Paths', function () {
    describe('Path 1: Direct Permission', function () {
        it('resolves permission assigned directly to subject', function () {
            $permission = Permission::create(['name' => 'direct:permission', 'guard' => 'web']);
            $this->user->grantPermission($permission);

            expect($this->user->hasPermission('direct:permission'))->toBeTrue()
                ->and($this->user->hasDirectPermission('direct:permission'))->toBeTrue();
        });
    });

    describe('Path 2: Via Role', function () {
        it('resolves permission assigned to role', function () {
            $permission = Permission::create(['name' => 'role:permission', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->grantPermission($permission);
            $this->user->assignRole($role);

            expect($this->user->hasPermission('role:permission'))->toBeTrue()
                ->and($this->user->hasPermissionViaRole('role:permission'))->toBeTrue()
                ->and($this->user->hasDirectPermission('role:permission'))->toBeFalse();
        });
    });

    describe('Path 3: Via Capability (through role)', function () {
        it('resolves permission through role capability', function () {
            $permission = Permission::create(['name' => 'cap:permission', 'guard' => 'web']);
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $capability->grantPermission($permission);

            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            expect($this->user->hasPermission('cap:permission'))->toBeTrue()
                ->and($this->user->hasPermissionViaCapability('cap:permission'))->toBeTrue()
                ->and($this->user->hasDirectPermission('cap:permission'))->toBeFalse();
        });
    });

    describe('Path 4: Via Capability (direct)', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('resolves permission through direct capability', function () {
            $permission = Permission::create(['name' => 'direct-cap:permission', 'guard' => 'web']);
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $capability->grantPermission($permission);
            $this->user->assignCapability($capability);

            expect($this->user->hasPermission('direct-cap:permission'))->toBeTrue()
                ->and($this->user->hasPermissionViaCapability('direct-cap:permission'))->toBeTrue();
        });
    });

    describe('Combined Paths', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('getAllPermissions includes all resolution paths', function () {
            // Path 1: Direct permission
            $directPerm = Permission::create(['name' => 'direct:perm', 'guard' => 'web']);
            $this->user->grantPermission($directPerm);

            // Path 2: Role permission
            $rolePerm = Permission::create(['name' => 'role:perm', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->grantPermission($rolePerm);
            $this->user->assignRole($role);

            // Path 3: Capability via role permission
            $roleCapPerm = Permission::create(['name' => 'role-cap:perm', 'guard' => 'web']);
            $roleCap = Capability::create(['name' => 'role-cap', 'guard' => 'web']);
            $roleCap->grantPermission($roleCapPerm);
            $role->assignCapability($roleCap);

            // Path 4: Direct capability permission
            $directCapPerm = Permission::create(['name' => 'direct-cap:perm', 'guard' => 'web']);
            $directCap = Capability::create(['name' => 'direct-cap', 'guard' => 'web']);
            $directCap->grantPermission($directCapPerm);
            $this->user->assignCapability($directCap);

            $allPermissions = $this->user->getAllPermissions();
            $permissionNames = $allPermissions->pluck('name')->toArray();

            expect($allPermissions)->toHaveCount(4)
                ->and($permissionNames)->toContain('direct:perm')
                ->and($permissionNames)->toContain('role:perm')
                ->and($permissionNames)->toContain('role-cap:perm')
                ->and($permissionNames)->toContain('direct-cap:perm');
        });

        it('deduplicates permissions from multiple sources', function () {
            $permission = Permission::create(['name' => 'shared:perm', 'guard' => 'web']);

            // Same permission through multiple paths
            $this->user->grantPermission($permission);

            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->grantPermission($permission);
            $this->user->assignRole($role);

            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $capability->grantPermission($permission);
            $role->assignCapability($capability);

            $allPermissions = $this->user->getAllPermissions();

            expect($allPermissions)->toHaveCount(1)
                ->and($allPermissions->first()->name)->toBe('shared:perm');
        });

        it('hasPermission returns true for any valid path', function () {
            $perm1 = Permission::create(['name' => 'perm1', 'guard' => 'web']);
            $perm2 = Permission::create(['name' => 'perm2', 'guard' => 'web']);
            $perm3 = Permission::create(['name' => 'perm3', 'guard' => 'web']);
            $perm4 = Permission::create(['name' => 'perm4', 'guard' => 'web']);

            // Path 1
            $this->user->grantPermission($perm1);

            // Path 2
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->grantPermission($perm2);
            $this->user->assignRole($role);

            // Path 3
            $roleCap = Capability::create(['name' => 'role-cap', 'guard' => 'web']);
            $roleCap->grantPermission($perm3);
            $role->assignCapability($roleCap);

            // Path 4
            $directCap = Capability::create(['name' => 'direct-cap', 'guard' => 'web']);
            $directCap->grantPermission($perm4);
            $this->user->assignCapability($directCap);

            expect($this->user->hasPermission('perm1'))->toBeTrue()
                ->and($this->user->hasPermission('perm2'))->toBeTrue()
                ->and($this->user->hasPermission('perm3'))->toBeTrue()
                ->and($this->user->hasPermission('perm4'))->toBeTrue()
                ->and($this->user->hasPermission('nonexistent'))->toBeFalse();
        });
    });
});

describe('Role Permission Resolution with Capabilities', function () {
    it('role getAllPermissions includes capability permissions', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);

        // Direct role permission
        $directPerm = Permission::create(['name' => 'direct:perm', 'guard' => 'web']);
        $role->grantPermission($directPerm);

        // Capability permission
        $capPerm = Permission::create(['name' => 'cap:perm', 'guard' => 'web']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $capability->grantPermission($capPerm);
        $role->assignCapability($capability);

        $allPermissions = $role->getAllPermissions();
        $permissionNames = $allPermissions->pluck('name')->toArray();

        expect($allPermissions)->toHaveCount(2)
            ->and($permissionNames)->toContain('direct:perm')
            ->and($permissionNames)->toContain('cap:perm');
    });

    it('role getPermissionsViaCapabilities returns only capability permissions', function () {
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);

        // Direct role permission
        $directPerm = Permission::create(['name' => 'direct:perm', 'guard' => 'web']);
        $role->grantPermission($directPerm);

        // Capability permissions
        $capPerm1 = Permission::create(['name' => 'cap:perm1', 'guard' => 'web']);
        $capPerm2 = Permission::create(['name' => 'cap:perm2', 'guard' => 'web']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $capability->grantPermission([$capPerm1, $capPerm2]);
        $role->assignCapability($capability);

        $capabilityPermissions = $role->getPermissionsViaCapabilities();
        $permissionNames = $capabilityPermissions->pluck('name')->toArray();

        expect($capabilityPermissions)->toHaveCount(2)
            ->and($permissionNames)->toContain('cap:perm1')
            ->and($permissionNames)->toContain('cap:perm2')
            ->and($permissionNames)->not->toContain('direct:perm');
    });
});
