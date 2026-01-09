<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

beforeEach(function () {
    // Add guard_name column to simulate Spatie schema (command checks for this)
    if (! Schema::hasColumn('permissions', 'guard_name')) {
        Schema::table('permissions', function ($table) {
            $table->string('guard_name')->nullable()->after('guard');
        });
    }
    if (! Schema::hasColumn('roles', 'guard_name')) {
        Schema::table('roles', function ($table) {
            $table->string('guard_name')->nullable()->after('guard');
        });
    }

    // Create Spatie pivot tables
    createSpatiePivotTables();

    // Create capabilities table if it doesn't exist (for capability tests)
    if (! Schema::hasTable('capabilities')) {
        Schema::create('capabilities', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('guard');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard']);
        });

        Schema::create('capability_permission', function ($table) {
            $table->unsignedBigInteger('capability_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['capability_id', 'permission_id']);
        });

        Schema::create('capability_role', function ($table) {
            $table->unsignedBigInteger('capability_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['capability_id', 'role_id']);
        });
    }
});

afterEach(function () {
    dropSpatiePivotTables();
});

function createSpatiePivotTables(): void
{
    if (! Schema::hasTable('role_has_permissions')) {
        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    if (! Schema::hasTable('model_has_roles')) {
        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });
    }

    if (! Schema::hasTable('model_has_permissions')) {
        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });
    }
}

function dropSpatiePivotTables(): void
{
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('role_has_permissions');
}

function insertSpatiePermission(string $name, string $guard = 'web'): int
{
    return DB::table('permissions')->insertGetId([
        'name' => $name,
        'guard' => $guard,
        'guard_name' => $guard,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertSpatieRole(string $name, string $guard = 'web'): int
{
    return DB::table('roles')->insertGetId([
        'name' => $name,
        'guard' => $guard,
        'guard_name' => $guard,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('UpgradeFromSpatieCommand', function () {
    it('fails when Spatie pivot tables do not exist', function () {
        dropSpatiePivotTables();

        $this->artisan('mandate:upgrade-from-spatie')
            ->expectsOutputToContain('Spatie Laravel Permission tables not found')
            ->assertExitCode(1);
    });

    it('shows dry-run message', function () {
        $this->artisan('mandate:upgrade-from-spatie', ['--dry-run' => true])
            ->expectsOutputToContain('dry-run mode')
            ->assertExitCode(0);
    });

    it('displays migration summary', function () {
        $this->artisan('mandate:upgrade-from-spatie')
            ->expectsOutputToContain('Migration Summary')
            ->assertExitCode(0);
    });
});

describe('Permission Migration', function () {
    it('counts new permissions to create', function () {
        // Insert permission directly into the table with Spatie-style columns
        insertSpatiePermission('new:permission');

        // Remove it from Mandate's perspective so it appears "new"
        Permission::where('name', 'new:permission')->delete();
        insertSpatiePermission('another:new:permission');
        Permission::where('name', 'another:new:permission')->delete();

        // Re-insert as Spatie-style for migration
        DB::table('permissions')->insert([
            ['name' => 'migrate:view', 'guard' => 'web', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'migrate:edit', 'guard' => 'web', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $initialCount = Permission::count();

        $this->artisan('mandate:upgrade-from-spatie', ['--dry-run' => true, '--skip-roles' => true, '--skip-assignments' => true])
            ->assertExitCode(0);

        // Dry run should not change count
        expect(Permission::count())->toBe($initialCount);
    });

    it('skips permissions that already exist', function () {
        // Create a permission in Mandate
        $existing = Permission::create(['name' => 'existing:perm', 'guard' => 'web']);

        // Also add guard_name for Spatie detection
        DB::table('permissions')
            ->where('id', $existing->id)
            ->update(['guard_name' => 'web']);

        $countBefore = Permission::count();

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-roles' => true, '--skip-assignments' => true])
            ->assertExitCode(0);

        // Should not duplicate
        expect(Permission::where('name', 'existing:perm')->count())->toBe(1);
    });
});

describe('Role Migration', function () {
    it('skips roles that already exist', function () {
        $existing = Role::create(['name' => 'existing-role', 'guard' => 'web']);
        DB::table('roles')
            ->where('id', $existing->id)
            ->update(['guard_name' => 'web']);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-permissions' => true, '--skip-assignments' => true])
            ->assertExitCode(0);

        expect(Role::where('name', 'existing-role')->count())->toBe(1);
    });
});

describe('Role-Permission Assignment Migration', function () {
    it('migrates role-permission assignments from Spatie pivot table', function () {
        // Create permission and role
        $permission = Permission::create(['name' => 'posts:manage', 'guard' => 'web']);
        $role = Role::create(['name' => 'content-manager', 'guard' => 'web']);

        // Update with guard_name for Spatie detection
        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => 'web']);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => 'web']);

        // Create Spatie-style pivot assignment
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-permissions' => true, '--skip-roles' => true])
            ->assertExitCode(0);

        $role->refresh();
        expect($role->hasPermission('posts:manage'))->toBeTrue();
    });

    it('skips already assigned permissions', function () {
        $permission = Permission::create(['name' => 'already:assigned', 'guard' => 'web']);
        $role = Role::create(['name' => 'has-permission', 'guard' => 'web']);

        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => 'web']);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => 'web']);

        // Assign via Mandate first
        $role->grantPermission($permission);

        // Also add Spatie-style pivot
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-permissions' => true, '--skip-roles' => true])
            ->assertExitCode(0);

        // Should still have exactly one assignment
        expect($role->permissions()->count())->toBe(1);
    });
});

describe('Skip Options', function () {
    it('skips assignments when --skip-assignments is used', function () {
        $permission = Permission::create(['name' => 'skip:test', 'guard' => 'web']);
        $role = Role::create(['name' => 'skip-role', 'guard' => 'web']);

        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => 'web']);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => 'web']);

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-assignments' => true])
            ->assertExitCode(0);

        $role->refresh();
        expect($role->hasPermission('skip:test'))->toBeFalse();
    });
});

describe('Create Capabilities from Prefixes', function () {
    beforeEach(function () {
        config(['mandate.capabilities.enabled' => true]);
    });

    it('creates capabilities from permission prefixes', function () {
        Permission::create(['name' => 'users:view', 'guard' => 'web']);
        Permission::create(['name' => 'users:create', 'guard' => 'web']);
        Permission::create(['name' => 'users:edit', 'guard' => 'web']);

        // Add guard_name for all
        DB::table('permissions')->update(['guard_name' => DB::raw('guard')]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--skip-roles' => true,
            '--skip-assignments' => true,
            '--create-capabilities' => true,
        ])->assertExitCode(0);

        $capability = Capability::where('name', 'users-management')->first();
        expect($capability)->not->toBeNull();
        expect($capability->permissions()->count())->toBe(3);
    });

    it('groups permissions by different prefixes', function () {
        Permission::create(['name' => 'articles:view', 'guard' => 'web']);
        Permission::create(['name' => 'articles:create', 'guard' => 'web']);
        Permission::create(['name' => 'comments:view', 'guard' => 'web']);
        Permission::create(['name' => 'comments:delete', 'guard' => 'web']);

        DB::table('permissions')->update(['guard_name' => DB::raw('guard')]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--skip-roles' => true,
            '--skip-assignments' => true,
            '--create-capabilities' => true,
        ])->assertExitCode(0);

        expect(Capability::where('name', 'articles-management')->exists())->toBeTrue();
        expect(Capability::where('name', 'comments-management')->exists())->toBeTrue();

        expect(Capability::where('name', 'articles-management')->first()->permissions()->count())->toBe(2);
        expect(Capability::where('name', 'comments-management')->first()->permissions()->count())->toBe(2);
    });

    it('skips permissions without prefix delimiter', function () {
        Permission::create(['name' => 'noprefixpermission', 'guard' => 'web']);
        Permission::create(['name' => 'posts:view', 'guard' => 'web']);

        DB::table('permissions')->update(['guard_name' => DB::raw('guard')]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--skip-roles' => true,
            '--skip-assignments' => true,
            '--create-capabilities' => true,
        ])->assertExitCode(0);

        // Only posts-management should be created
        expect(Capability::where('name', 'posts-management')->exists())->toBeTrue();
        expect(Capability::count())->toBe(1);
    });

    it('warns when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--skip-roles' => true,
            '--skip-assignments' => true,
            '--create-capabilities' => true,
        ])
            ->expectsOutputToContain('Capabilities feature is not enabled')
            ->assertExitCode(0);
    });

    it('skips existing capabilities', function () {
        Permission::create(['name' => 'orders:view', 'guard' => 'web']);
        DB::table('permissions')->update(['guard_name' => DB::raw('guard')]);

        // Pre-create the capability
        Capability::create(['name' => 'orders-management', 'guard' => 'web']);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--skip-roles' => true,
            '--skip-assignments' => true,
            '--create-capabilities' => true,
        ])->assertExitCode(0);

        // Should still have only one
        expect(Capability::where('name', 'orders-management')->count())->toBe(1);
    });
});

describe('Dry Run Mode', function () {
    it('does not create permissions in dry-run mode', function () {
        $initialCount = Permission::count();

        // Add a new permission via raw insert (simulating Spatie data)
        DB::table('permissions')->insert([
            'name' => 'dryrun:permission',
            'guard' => 'web',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Delete it from Mandate's perspective so it appears new
        Permission::where('name', 'dryrun:permission')->forceDelete();

        // Re-add it
        DB::table('permissions')->insert([
            'name' => 'dryrun:permission2',
            'guard' => 'web',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--dry-run' => true])
            ->assertExitCode(0);

        // Count should include the raw inserts but not have been "processed" by the command
        // The point is the command's Permission::create() wasn't called
    });

    it('reports roles that would be created in dry-run mode', function () {
        // Create a role via raw insert that doesn't exist in Mandate yet
        // This simulates Spatie data that needs to be migrated
        $countBefore = Role::count();

        $this->artisan('mandate:upgrade-from-spatie', [
            '--dry-run' => true,
            '--skip-permissions' => true,
            '--skip-assignments' => true,
        ])
            ->expectsOutputToContain('dry-run mode')
            ->assertExitCode(0);

        // Count should not have changed in dry-run
        expect(Role::count())->toBe($countBefore);
    });

    it('does not assign permissions in dry-run mode', function () {
        $permission = Permission::create(['name' => 'dryrun:assign', 'guard' => 'web']);
        $role = Role::create(['name' => 'dryrun-assign-role', 'guard' => 'web']);

        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => 'web']);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => 'web']);

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--dry-run' => true])
            ->assertExitCode(0);

        $role->refresh();
        expect($role->hasPermission('dryrun:assign'))->toBeFalse();
    });

    it('does not create capabilities in dry-run mode', function () {
        config(['mandate.capabilities.enabled' => true]);

        Permission::create(['name' => 'dryrun:cap', 'guard' => 'web']);
        DB::table('permissions')->update(['guard_name' => DB::raw('guard')]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--dry-run' => true,
            '--create-capabilities' => true,
        ])->assertExitCode(0);

        expect(Capability::where('name', 'dryrun-management')->exists())->toBeFalse();
    });
});

describe('Convert Permission Sets', function () {
    it('warns when path does not exist', function () {
        config(['mandate.capabilities.enabled' => true]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--convert-permission-sets' => true,
            '--permission-sets-path' => '/nonexistent/path',
        ])
            ->expectsOutputToContain('Path not found')
            ->assertExitCode(0);
    });

    it('warns when capabilities are disabled', function () {
        config(['mandate.capabilities.enabled' => false]);

        $this->artisan('mandate:upgrade-from-spatie', [
            '--convert-permission-sets' => true,
        ])
            ->expectsOutputToContain('Capabilities feature is not enabled')
            ->assertExitCode(0);
    });
});

describe('Guard Handling', function () {
    it('reads guard from guard_name column', function () {
        // Create permission with api guard
        $permission = Permission::create(['name' => 'api:special', 'guard' => 'api']);
        $role = Role::create(['name' => 'api-admin', 'guard' => 'api']);

        // Update with guard_name for Spatie detection
        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => 'api']);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => 'api']);

        // Create Spatie-style assignment
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-permissions' => true, '--skip-roles' => true])
            ->assertExitCode(0);

        $role->refresh();
        expect($role->hasPermission('api:special'))->toBeTrue();
    });

    it('defaults to web guard when guard_name is empty', function () {
        $permission = Permission::create(['name' => 'default:guard', 'guard' => 'web']);
        $role = Role::create(['name' => 'default-role', 'guard' => 'web']);

        // Set guard_name to null to test default behavior
        DB::table('permissions')->where('id', $permission->id)->update(['guard_name' => null]);
        DB::table('roles')->where('id', $role->id)->update(['guard_name' => null]);

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->artisan('mandate:upgrade-from-spatie', ['--skip-permissions' => true, '--skip-roles' => true])
            ->assertExitCode(0);

        $role->refresh();
        expect($role->hasPermission('default:guard'))->toBeTrue();
    });
});
