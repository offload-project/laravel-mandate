<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OffloadProject\Mandate\Models\Capability;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;

describe('Model ID Type', function () {
    describe('UUID mode', function () {
        beforeEach(function () {
            $this->enableUuids();
        });

        it('creates permissions with UUID primary keys', function () {
            $permission = Permission::create(['name' => 'test-permission']);

            expect($permission->id)->toBeString();
            expect(Str::isUuid($permission->id))->toBeTrue();
            expect($permission->getKeyType())->toBe('string');
            expect($permission->getIncrementing())->toBeFalse();
        });

        it('creates roles with UUID primary keys', function () {
            $role = Role::create(['name' => 'test-role']);

            expect($role->id)->toBeString();
            expect(Str::isUuid($role->id))->toBeTrue();
            expect($role->getKeyType())->toBe('string');
            expect($role->getIncrementing())->toBeFalse();
        });

        it('can assign permissions to roles with UUIDs', function () {
            $permission = Permission::create(['name' => 'test-permission']);
            $role = Role::create(['name' => 'test-role']);

            $role->grantPermission($permission);

            expect($role->hasPermission($permission))->toBeTrue();
            expect($role->permissions)->toHaveCount(1);
        });

        it('can assign roles to users with UUIDs', function () {
            $user = OffloadProject\Mandate\Tests\Fixtures\User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            $role = Role::create(['name' => 'test-role']);

            $user->assignRole($role);

            expect($user->hasRole($role))->toBeTrue();
        });
    });

    describe('ULID mode', function () {
        beforeEach(function () {
            $this->enableUlids();
        });

        it('creates permissions with ULID primary keys', function () {
            $permission = Permission::create(['name' => 'test-permission']);

            expect($permission->id)->toBeString();
            expect(Str::isUlid($permission->id))->toBeTrue();
            expect($permission->getKeyType())->toBe('string');
            expect($permission->getIncrementing())->toBeFalse();
        });

        it('creates roles with ULID primary keys', function () {
            $role = Role::create(['name' => 'test-role']);

            expect($role->id)->toBeString();
            expect(Str::isUlid($role->id))->toBeTrue();
            expect($role->getKeyType())->toBe('string');
            expect($role->getIncrementing())->toBeFalse();
        });

        it('can assign permissions to roles with ULIDs', function () {
            $permission = Permission::create(['name' => 'test-permission']);
            $role = Role::create(['name' => 'test-role']);

            $role->grantPermission($permission);

            expect($role->hasPermission($permission))->toBeTrue();
            expect($role->permissions)->toHaveCount(1);
        });

        it('can assign roles to users with ULIDs', function () {
            $user = OffloadProject\Mandate\Tests\Fixtures\User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            $role = Role::create(['name' => 'test-role']);

            $user->assignRole($role);

            expect($user->hasRole($role))->toBeTrue();
        });
    });

    describe('default int mode', function () {
        it('creates permissions with integer primary keys', function () {
            $permission = Permission::create(['name' => 'test-permission']);

            expect($permission->id)->toBeInt();
            expect($permission->getKeyType())->toBe('int');
            expect($permission->getIncrementing())->toBeTrue();
        });

        it('creates roles with integer primary keys', function () {
            $role = Role::create(['name' => 'test-role']);

            expect($role->id)->toBeInt();
            expect($role->getKeyType())->toBe('int');
            expect($role->getIncrementing())->toBeTrue();
        });
    });

    describe('morph ID type', function () {
        it('defaults morph_id_type to model_id_type when not set', function () {
            config(['mandate.model_id_type' => 'uuid']);
            config(['mandate.morph_id_type' => null]);
            $this->recreateTables();

            $columns = collect(Schema::getColumns('role_subject'));
            $subjectIdCol = $columns->firstWhere('name', 'subject_id');

            // Should fall back to model_id_type (uuid → varchar in SQLite)
            expect($subjectIdCol['type_name'])->toBe('varchar');
        });

        it('creates uuid morph columns when morph_id_type is uuid', function () {
            config(['mandate.morph_id_type' => 'uuid']);
            $this->recreateTables();

            $columns = collect(Schema::getColumns('role_subject'));
            $subjectIdCol = $columns->firstWhere('name', 'subject_id');

            expect($subjectIdCol['type_name'])->toBe('varchar');
        });

        it('creates integer morph columns by default', function () {
            $columns = collect(Schema::getColumns('role_subject'));
            $subjectIdCol = $columns->firstWhere('name', 'subject_id');

            expect($subjectIdCol['type_name'])->toBe('integer');
        });

        it('creates uuid context morph columns when morph_id_type is uuid', function () {
            config(['mandate.morph_id_type' => 'uuid']);
            $this->enableContext();

            $columns = collect(Schema::getColumns('permissions'));
            $contextIdCol = $columns->firstWhere('name', 'context_id');

            expect($contextIdCol['type_name'])->toBe('varchar');
        });

        it('creates integer context morph columns by default', function () {
            $this->enableContext();

            $columns = collect(Schema::getColumns('permissions'));
            $contextIdCol = $columns->firstWhere('name', 'context_id');

            expect($contextIdCol['type_name'])->toBe('integer');
        });

        it('creates uuid morph columns on capability_subject when morph_id_type is uuid', function () {
            config(['mandate.morph_id_type' => 'uuid']);
            $this->enableUuids();
            $this->enableCapabilities();

            $columns = collect(Schema::getColumns('capability_subject'));
            $subjectIdCol = $columns->firstWhere('name', 'subject_id');

            expect($subjectIdCol['type_name'])->toBe('varchar');
        });

        it('allows morph_id_type to differ from model_id_type', function () {
            config(['mandate.model_id_type' => 'int']);
            config(['mandate.morph_id_type' => 'uuid']);
            $this->recreateTables();

            // Mandate model PKs should be integer
            $permCols = collect(Schema::getColumns('permissions'));
            $idCol = $permCols->firstWhere('name', 'id');
            expect($idCol['type_name'])->toBe('integer');

            // Subject morph should be uuid (varchar in SQLite)
            $pivotCols = collect(Schema::getColumns('role_subject'));
            $subjectIdCol = $pivotCols->firstWhere('name', 'subject_id');
            expect($subjectIdCol['type_name'])->toBe('varchar');
        });
    });

    describe('capabilities with UUID', function () {
        beforeEach(function () {
            $this->enableUuids();
            $this->enableCapabilities();
        });

        it('creates capabilities with UUID primary keys', function () {
            $capability = Capability::create(['name' => 'test-capability']);

            expect($capability->id)->toBeString();
            expect(Str::isUuid($capability->id))->toBeTrue();
            expect($capability->getKeyType())->toBe('string');
            expect($capability->getIncrementing())->toBeFalse();
        });

        it('can assign permissions to capabilities with UUIDs', function () {
            $permission = Permission::create(['name' => 'test-permission']);
            $capability = Capability::create(['name' => 'test-capability']);

            $capability->grantPermission($permission);

            expect($capability->hasPermission($permission))->toBeTrue();
        });
    });
});
