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

describe('HasCapabilities via Roles', function () {
    describe('checking capabilities via roles', function () {
        it('can check if user has capability via role', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);

            expect($this->user->hasCapability('manage-posts'))->toBeFalse();

            $this->user->assignRole($role);

            expect($this->user->hasCapability('manage-posts'))->toBeTrue();
        });

        it('can check if user has any capability', function () {
            $cap1 = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $cap2 = Capability::create(['name' => 'manage-users', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($cap1);
            $this->user->assignRole($role);

            expect($this->user->hasAnyCapability(['manage-posts', 'manage-users']))->toBeTrue()
                ->and($this->user->hasAnyCapability(['manage-settings']))->toBeFalse();
        });

        it('can check if user has all capabilities', function () {
            $cap1 = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $cap2 = Capability::create(['name' => 'manage-users', 'guard' => 'web']);
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $role->assignCapability([$cap1, $cap2]);
            $this->user->assignRole($role);

            expect($this->user->hasAllCapabilities(['manage-posts', 'manage-users']))->toBeTrue()
                ->and($this->user->hasAllCapabilities(['manage-posts', 'manage-settings']))->toBeFalse();
        });

        it('hasCapabilityViaRole checks capability through roles', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            expect($this->user->hasCapabilityViaRole('manage-posts'))->toBeTrue()
                ->and($this->user->hasCapabilityViaRole('manage-users'))->toBeFalse();
        });
    });

    describe('getting capabilities via roles', function () {
        it('can get all capabilities via roles', function () {
            $cap1 = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $cap2 = Capability::create(['name' => 'manage-users', 'guard' => 'web']);
            $role = Role::create(['name' => 'admin', 'guard' => 'web']);
            $role->assignCapability([$cap1, $cap2]);
            $this->user->assignRole($role);

            $capabilities = $this->user->getCapabilitiesViaRoles();

            expect($capabilities)->toHaveCount(2);
        });

        it('getAllCapabilities includes role capabilities', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            $capabilities = $this->user->getAllCapabilities();

            expect($capabilities)->toHaveCount(1)
                ->and($capabilities->first()->name)->toBe('manage-posts');
        });

        it('deduplicates capabilities from multiple roles', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $role1 = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role2 = Role::create(['name' => 'writer', 'guard' => 'web']);
            $role1->assignCapability($capability);
            $role2->assignCapability($capability);
            $this->user->assignRole([$role1, $role2]);

            $capabilities = $this->user->getAllCapabilities();

            expect($capabilities)->toHaveCount(1);
        });
    });

    describe('permissions via capabilities', function () {
        it('can get permissions via capabilities', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Permission::create(['name' => 'posts:view', 'guard' => 'web']);
            Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            $capability->grantPermission(['posts:view', 'posts:edit']);

            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            $permissions = $this->user->getPermissionsViaCapabilities();

            expect($permissions)->toHaveCount(2);
        });

        it('hasPermissionViaCapability checks permission through capabilities', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            $capability->grantPermission($permission);

            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            expect($this->user->hasPermissionViaCapability('posts:edit'))->toBeTrue()
                ->and($this->user->hasPermissionViaCapability('posts:delete'))->toBeFalse();
        });

        it('hasPermission includes capability permissions', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            $capability->grantPermission($permission);

            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($capability);
            $this->user->assignRole($role);

            expect($this->user->hasPermission('posts:edit'))->toBeTrue();
        });

        it('getAllPermissions includes capability permissions', function () {
            // Direct permission
            $directPerm = Permission::create(['name' => 'direct:perm', 'guard' => 'web']);
            $this->user->grantPermission($directPerm);

            // Role permission
            $rolePerm = Permission::create(['name' => 'role:perm', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->grantPermission($rolePerm);
            $this->user->assignRole($role);

            // Capability permission
            $capPerm = Permission::create(['name' => 'cap:perm', 'guard' => 'web']);
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $capability->grantPermission($capPerm);
            $role->assignCapability($capability);

            $permissions = $this->user->getAllPermissions();

            expect($permissions)->toHaveCount(3)
                ->and($permissions->pluck('name')->toArray())
                ->toContain('direct:perm', 'role:perm', 'cap:perm');
        });
    });
});

describe('Direct Capability Assignment', function () {
    beforeEach(function () {
        $this->enableDirectCapabilityAssignment();
    });

    describe('assigning capabilities directly', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('can assign capability directly to user', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            $this->user->assignCapability($capability);

            expect($this->user->capabilities)->toHaveCount(1)
                ->and($this->user->capabilities->first()->name)->toBe('manage-posts');
        });

        it('can assign multiple capabilities', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Capability::create(['name' => 'manage-users', 'guard' => 'web']);

            $this->user->assignCapability(['manage-posts', 'manage-users']);

            expect($this->user->capabilities)->toHaveCount(2);
        });

        it('does not duplicate capabilities', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

            $this->user->assignCapability($capability);
            $this->user->assignCapability($capability);

            expect($this->user->capabilities)->toHaveCount(1);
        });
    });

    describe('removing capabilities directly', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });
        it('can remove capability from user', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $this->user->assignCapability($capability);

            $this->user->removeCapability($capability);
            $this->user->refresh();

            expect($this->user->capabilities)->toHaveCount(0);
        });
    });

    describe('syncing capabilities directly', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('can sync capabilities', function () {
            Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            Capability::create(['name' => 'manage-users', 'guard' => 'web']);
            Capability::create(['name' => 'manage-settings', 'guard' => 'web']);

            $this->user->assignCapability(['manage-posts', 'manage-users']);
            expect($this->user->capabilities)->toHaveCount(2);

            $this->user->syncCapabilities(['manage-settings']);
            $this->user->refresh();

            expect($this->user->capabilities)->toHaveCount(1)
                ->and($this->user->capabilities->first()->name)->toBe('manage-settings');
        });
    });

    describe('checking direct capabilities', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('hasDirectCapability checks direct assignment only', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $this->user->assignCapability($capability);

            expect($this->user->hasDirectCapability('manage-posts'))->toBeTrue()
                ->and($this->user->hasDirectCapability('manage-users'))->toBeFalse();
        });

        it('hasCapability includes direct capabilities', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $this->user->assignCapability($capability);

            expect($this->user->hasCapability('manage-posts'))->toBeTrue();
        });

        it('getAllCapabilities includes direct capabilities', function () {
            // Direct capability
            $directCap = Capability::create(['name' => 'direct-cap', 'guard' => 'web']);
            $this->user->assignCapability($directCap);

            // Role capability
            $roleCap = Capability::create(['name' => 'role-cap', 'guard' => 'web']);
            $role = Role::create(['name' => 'editor', 'guard' => 'web']);
            $role->assignCapability($roleCap);
            $this->user->assignRole($role);

            $capabilities = $this->user->getAllCapabilities();

            expect($capabilities)->toHaveCount(2)
                ->and($capabilities->pluck('name')->toArray())
                ->toContain('direct-cap', 'role-cap');
        });
    });

    describe('permissions via direct capabilities', function () {
        beforeEach(function () {
            $this->enableDirectCapabilityAssignment();
        });

        it('hasPermissionViaCapability checks direct capability permissions', function () {
            $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
            $permission = Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
            $capability->grantPermission($permission);
            $this->user->assignCapability($capability);

            expect($this->user->hasPermissionViaCapability('posts:edit'))->toBeTrue();
        });
    });
});

describe('Capabilities Disabled', function () {
    it('returns false for hasCapability when disabled', function () {
        // Note: capabilities are NOT enabled here
        expect($this->user->hasCapability('anything'))->toBeFalse();
    });

    it('returns false for hasAnyCapability when disabled', function () {
        expect($this->user->hasAnyCapability(['anything']))->toBeFalse();
    });

    it('returns false for hasAllCapabilities when disabled', function () {
        expect($this->user->hasAllCapabilities(['anything']))->toBeFalse();
    });

    it('returns empty collection for getAllCapabilities when disabled', function () {
        expect($this->user->getAllCapabilities())->toBeEmpty();
    });
});

describe('Role Capability Methods', function () {
    beforeEach(function () {
        $this->role = Role::create(['name' => 'editor', 'guard' => 'web']);
    });

    it('can assign capability to role', function () {
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->role->assignCapability($capability);

        expect($this->role->capabilities)->toHaveCount(1)
            ->and($this->role->capabilities->first()->name)->toBe('manage-posts');
    });

    it('can assign capability by name to role', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        $this->role->assignCapability('manage-posts');

        expect($this->role->capabilities)->toHaveCount(1);
    });

    it('can assign multiple capabilities to role', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);

        $this->role->assignCapability(['manage-posts', 'manage-users']);

        expect($this->role->capabilities)->toHaveCount(2);
    });

    it('can remove capability from role', function () {
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $this->role->assignCapability($capability);

        $this->role->removeCapability($capability);
        $this->role->refresh();

        expect($this->role->capabilities)->toHaveCount(0);
    });

    it('can sync capabilities on role', function () {
        Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        Capability::create(['name' => 'manage-users', 'guard' => 'web']);
        Capability::create(['name' => 'manage-settings', 'guard' => 'web']);

        $this->role->assignCapability(['manage-posts', 'manage-users']);
        expect($this->role->capabilities)->toHaveCount(2);

        $this->role->syncCapabilities(['manage-settings']);
        $this->role->refresh();

        expect($this->role->capabilities)->toHaveCount(1)
            ->and($this->role->capabilities->first()->name)->toBe('manage-settings');
    });

    it('can check if role has capability', function () {
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);

        expect($this->role->hasCapability('manage-posts'))->toBeFalse();

        $this->role->assignCapability($capability);

        expect($this->role->hasCapability('manage-posts'))->toBeTrue();
    });

    it('role getAllPermissions includes capability permissions', function () {
        // Direct role permission
        $directPerm = Permission::create(['name' => 'direct:perm', 'guard' => 'web']);
        $this->role->grantPermission($directPerm);

        // Capability permission
        $capPerm = Permission::create(['name' => 'cap:perm', 'guard' => 'web']);
        $capability = Capability::create(['name' => 'manage-posts', 'guard' => 'web']);
        $capability->grantPermission($capPerm);
        $this->role->assignCapability($capability);

        $permissions = $this->role->getAllPermissions();

        expect($permissions)->toHaveCount(2)
            ->and($permissions->pluck('name')->toArray())
            ->toContain('direct:perm', 'cap:perm');
    });
});
