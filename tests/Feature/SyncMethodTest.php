<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use OffloadProject\Mandate\Events\MandateSynced;
use OffloadProject\Mandate\Events\PermissionsSynced;
use OffloadProject\Mandate\Events\RolesSynced;
use OffloadProject\Mandate\Facades\Mandate;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\SyncResult;

describe('Mandate::sync()', function () {
    beforeEach(function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../Fixtures/CodeFirst']);

        // Reset static caches between tests
        Permission::resetLabelColumnCache();
        Role::resetLabelColumnCache();
    });

    it('throws exception when code-first is disabled', function () {
        config(['mandate.code_first.enabled' => false]);

        Mandate::sync();
    })->throws(RuntimeException::class, 'Code-first mode is not enabled');

    it('returns a SyncResult instance', function () {
        $result = Mandate::sync();

        expect($result)->toBeInstanceOf(SyncResult::class);
    });

    it('syncs all definitions by default', function () {
        $result = Mandate::sync();

        // Check expected permissions exist
        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:create')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:edit')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:delete')->exists())->toBeTrue();

        // Check expected roles exist
        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::where('name', 'editor')->exists())->toBeTrue();
        expect(Role::where('name', 'viewer')->exists())->toBeTrue();

        // Verify counts are at least what we expect
        expect($result->permissionsCreated)->toBeGreaterThanOrEqual(4);
        expect($result->rolesCreated)->toBeGreaterThanOrEqual(3);
        expect($result->hasChanges())->toBeTrue();
    });

    it('syncs only permissions when permissions flag is true', function () {
        $result = Mandate::sync(permissions: true);

        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
        expect(Permission::count())->toBeGreaterThanOrEqual(4);
        expect(Role::count())->toBe(0);

        expect($result->permissionsCreated)->toBeGreaterThanOrEqual(4);
        expect($result->rolesCreated)->toBe(0);
    });

    it('syncs only roles when roles flag is true', function () {
        $result = Mandate::sync(roles: true);

        expect(Permission::count())->toBe(0);
        expect(Role::where('name', 'admin')->exists())->toBeTrue();
        expect(Role::count())->toBeGreaterThanOrEqual(3);

        expect($result->permissionsCreated)->toBe(0);
        expect($result->rolesCreated)->toBeGreaterThanOrEqual(3);
    });

    it('updates existing records with new labels', function () {
        // Run label column migration first
        $migrationPath = __DIR__.'/../../database/migrations';
        $migration = include $migrationPath.'/2024_01_01_000003_add_label_description_to_mandate_tables.php';
        $migration->up();

        // Reset the static cache
        Permission::resetLabelColumnCache();

        // Create a permission without label
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $result = Mandate::sync(permissions: true);

        $permission = Permission::where('name', 'article:view')->first();
        expect($permission->label)->toBe('View Articles');
        expect($result->permissionsUpdated)->toBeGreaterThanOrEqual(1);
        expect($result->permissionsCreated)->toBeGreaterThanOrEqual(3);
    });

    it('supports guard filter', function () {
        $result = Mandate::sync(permissions: true, guard: 'api');

        // No permissions should match 'api' guard in fixtures
        expect(Permission::count())->toBe(0);
        expect($result->permissionsCreated)->toBe(0);
    });

    it('dispatches sync events', function () {
        Event::fake([PermissionsSynced::class, RolesSynced::class, MandateSynced::class]);

        Mandate::sync();

        Event::assertDispatched(PermissionsSynced::class);
        Event::assertDispatched(RolesSynced::class);
        Event::assertDispatched(MandateSynced::class);
    });

    it('does not dispatch events for types not synced', function () {
        Event::fake([PermissionsSynced::class, RolesSynced::class, MandateSynced::class]);

        Mandate::sync(permissions: true);

        Event::assertDispatched(PermissionsSynced::class);
        Event::assertNotDispatched(RolesSynced::class);
        Event::assertDispatched(MandateSynced::class);
    });
})->skip(fn () => ! class_exists(OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer::class), 'Code-first not implemented');

describe('Mandate::sync() with seed', function () {
    it('does not dispatch sync events in seed-only mode', function () {
        config(['mandate.code_first.enabled' => false]);

        Event::fake([PermissionsSynced::class, RolesSynced::class, MandateSynced::class]);

        config(['mandate.assignments' => [
            'seed-role' => [
                'permissions' => ['seed:permission'],
            ],
        ]]);

        Mandate::sync(seed: true);

        // No sync events should be dispatched in seed-only mode
        Event::assertNotDispatched(PermissionsSynced::class);
        Event::assertNotDispatched(RolesSynced::class);
        Event::assertNotDispatched(MandateSynced::class);
    });

    it('works without code-first enabled', function () {
        config(['mandate.code_first.enabled' => false]);

        // Create role and permission in database
        $role = Role::create(['name' => 'admin', 'guard' => 'web']);
        $permission = Permission::create(['name' => 'user:manage', 'guard' => 'web']);

        // Configure assignments
        config(['mandate.assignments' => [
            'admin' => [
                'permissions' => ['user:manage'],
            ],
        ]]);

        $result = Mandate::sync(seed: true);

        $role->refresh();
        expect($role->hasPermission('user:manage'))->toBeTrue();
        expect($result->assignmentsSeeded)->toBeTrue();
    });

    it('seeds role-permission assignments from config', function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../Fixtures/CodeFirst']);

        // Create role and permission
        $role = Role::create(['name' => 'editor', 'guard' => 'web']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        // Configure assignments
        config(['mandate.assignments' => [
            'editor' => [
                'permissions' => ['article:view', 'article:edit'],
            ],
        ]]);

        Mandate::sync(seed: true);

        $role->refresh();
        expect($role->hasPermission('article:view'))->toBeTrue();
        expect($role->hasPermission('article:edit'))->toBeTrue();
    });

    it('creates roles and permissions that do not exist', function () {
        config(['mandate.code_first.enabled' => false]);

        // Configure assignments for non-existent role and permissions
        config(['mandate.assignments' => [
            'new-role' => [
                'permissions' => ['new:permission', 'another:permission'],
            ],
        ]]);

        Mandate::sync(seed: true);

        // Role should be created
        $role = Role::where('name', 'new-role')->first();
        expect($role)->not->toBeNull();

        // Permissions should be created and assigned
        expect(Permission::where('name', 'new:permission')->exists())->toBeTrue();
        expect(Permission::where('name', 'another:permission')->exists())->toBeTrue();
        expect($role->hasPermission('new:permission'))->toBeTrue();
        expect($role->hasPermission('another:permission'))->toBeTrue();
    });

    it('seeds assignments when combined with permissions flag', function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../Fixtures/CodeFirst']);

        // Create a role for assignment
        $role = Role::create(['name' => 'combined-role', 'guard' => 'web']);

        config(['mandate.assignments' => [
            'combined-role' => [
                'permissions' => ['article:view'],
            ],
        ]]);

        // Run with both permissions and seed
        $result = Mandate::sync(permissions: true, seed: true);

        // Permissions should be synced from code-first
        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();

        // Assignments should also be seeded
        $role->refresh();
        expect($role->hasPermission('article:view'))->toBeTrue();
        expect($result->assignmentsSeeded)->toBeTrue();
    });

    it('creates and assigns capabilities when capabilities are enabled', function () {
        $this->enableCapabilities();
        config(['mandate.code_first.enabled' => false]);

        config(['mandate.assignments' => [
            'cap-role' => [
                'permissions' => ['cap:permission'],
                'capabilities' => ['test-capability', 'another-capability'],
            ],
        ]]);

        Mandate::sync(seed: true);

        // Role should be created
        $role = Role::where('name', 'cap-role')->first();
        expect($role)->not->toBeNull();

        // Capabilities should be created
        $capabilityClass = config('mandate.models.capability');
        expect($capabilityClass::where('name', 'test-capability')->exists())->toBeTrue();
        expect($capabilityClass::where('name', 'another-capability')->exists())->toBeTrue();

        // Capabilities should be assigned to role
        expect($role->hasCapability('test-capability'))->toBeTrue();
        expect($role->hasCapability('another-capability'))->toBeTrue();
    });

    it('syncs capability-permission relationships from Capability attributes', function () {
        $this->enableCapabilities();
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../Fixtures/CodeFirst']);

        Mandate::sync(permissions: true);

        $capabilityClass = config('mandate.models.capability');

        // UserPermissions has class-level #[Capability('user-management')]
        // So user:view, user:edit, user:delete should have user-management capability
        $userManagement = $capabilityClass::where('name', 'user-management')->first();
        expect($userManagement)->not->toBeNull();
        expect($userManagement->hasPermission('user:view'))->toBeTrue();
        expect($userManagement->hasPermission('user:edit'))->toBeTrue();
        expect($userManagement->hasPermission('user:delete'))->toBeTrue();

        // user:delete also has constant-level #[Capability('admin-only')]
        $adminOnly = $capabilityClass::where('name', 'admin-only')->first();
        expect($adminOnly)->not->toBeNull();
        expect($adminOnly->hasPermission('user:delete'))->toBeTrue();

        // Verify pivot table records
        $pivotTable = config('mandate.tables.capability_permission', 'capability_permission');
        $pivotCount = Illuminate\Support\Facades\DB::table($pivotTable)->count();
        expect($pivotCount)->toBe(4); // 3 for user-management + 1 for admin-only
    });

    it('syncs all discovered permissions when using seed with code-first enabled', function () {
        config(['mandate.code_first.enabled' => true]);
        config(['mandate.code_first.paths.permissions' => __DIR__.'/../Fixtures/CodeFirst']);
        config(['mandate.code_first.paths.roles' => __DIR__.'/../Fixtures/CodeFirst']);

        // Only assign one permission, but all should be synced
        config(['mandate.assignments' => [
            'partial-role' => [
                'permissions' => ['article:view'],
            ],
        ]]);

        Mandate::sync(seed: true);

        // All permissions from code-first should be synced, not just those in assignments
        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:create')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:edit')->exists())->toBeTrue();
        expect(Permission::where('name', 'article:delete')->exists())->toBeTrue();

        // But only article:view should be assigned to the role
        $role = Role::where('name', 'partial-role')->first();
        expect($role->hasPermission('article:view'))->toBeTrue();
        expect($role->hasPermission('article:create'))->toBeFalse();
    });

    it('respects guard filter when seeding', function () {
        config(['mandate.code_first.enabled' => false]);

        config(['mandate.assignments' => [
            'api-role' => [
                'permissions' => ['api:permission'],
            ],
        ]]);

        Mandate::sync(seed: true, guard: 'api');

        // Role should be created with api guard
        $role = Role::where('name', 'api-role')->where('guard', 'api')->first();
        expect($role)->not->toBeNull();

        // Permission should be created with api guard
        $permission = Permission::where('name', 'api:permission')->where('guard', 'api')->first();
        expect($permission)->not->toBeNull();
    });
})->skip(fn () => ! class_exists(OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer::class), 'Code-first not implemented');

describe('SyncResult', function () {
    it('calculates total created correctly', function () {
        $result = new SyncResult(
            permissionsCreated: 5,
            permissionsUpdated: 2,
            rolesCreated: 3,
            rolesUpdated: 1,
            capabilitiesCreated: 2,
            capabilitiesUpdated: 0,
        );

        expect($result->totalCreated())->toBe(10);
    });

    it('calculates total updated correctly', function () {
        $result = new SyncResult(
            permissionsCreated: 5,
            permissionsUpdated: 2,
            rolesCreated: 3,
            rolesUpdated: 1,
            capabilitiesCreated: 2,
            capabilitiesUpdated: 4,
        );

        expect($result->totalUpdated())->toBe(7);
    });

    it('calculates total correctly', function () {
        $result = new SyncResult(
            permissionsCreated: 5,
            permissionsUpdated: 2,
            rolesCreated: 3,
            rolesUpdated: 1,
        );

        expect($result->total())->toBe(11);
    });

    it('detects when changes were made', function () {
        $withChanges = new SyncResult(permissionsCreated: 1);
        $withoutChanges = new SyncResult();
        $withSeed = new SyncResult(assignmentsSeeded: true);

        expect($withChanges->hasChanges())->toBeTrue();
        expect($withoutChanges->hasChanges())->toBeFalse();
        expect($withSeed->hasChanges())->toBeTrue();
    });
});
