<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $idType = config('mandate.model_id_type', 'int');
        $contextEnabled = config('mandate.context.enabled', false);

        $this->createPermissionsTable($idType, $contextEnabled);
        $this->createRolesTable($idType, $contextEnabled);
        $this->createPermissionRoleTable($idType);
        $this->createPermissionSubjectTable($idType, $contextEnabled);
        $this->createRoleSubjectTable($idType, $contextEnabled);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.role_subject', 'role_subject'));
        Schema::dropIfExists(config('mandate.tables.permission_subject', 'permission_subject'));
        Schema::dropIfExists(config('mandate.tables.permission_role', 'permission_role'));
        Schema::dropIfExists(config('mandate.tables.roles', 'roles'));
        Schema::dropIfExists(config('mandate.tables.permissions', 'permissions'));
    }

    protected function createPermissionsTable(string $idType, bool $contextEnabled): void
    {
        $tableName = config('mandate.tables.permissions', 'permissions');

        Schema::create($tableName, function (Blueprint $table) use ($idType, $contextEnabled) {
            match ($idType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };

            $table->string('name');
            $table->string('guard');

            if ($contextEnabled) {
                $contextMorphName = config('mandate.column_names.context_morph_name', 'context');
                $contextTypeColumn = $contextMorphName.'_type';
                $contextIdColumn = $contextMorphName.'_id';

                $table->string($contextTypeColumn)->nullable();
                $table->unsignedBigInteger($contextIdColumn)->nullable();

                $table->unique(['name', 'guard', $contextTypeColumn, $contextIdColumn], 'permissions_unique');
                $table->index([$contextTypeColumn, $contextIdColumn], 'permissions_context_index');
            } else {
                $table->unique(['name', 'guard']);
            }

            $table->timestamps();
            $table->index('guard');
        });
    }

    protected function createRolesTable(string $idType, bool $contextEnabled): void
    {
        $tableName = config('mandate.tables.roles', 'roles');

        Schema::create($tableName, function (Blueprint $table) use ($idType, $contextEnabled) {
            match ($idType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };

            $table->string('name');
            $table->string('guard');

            if ($contextEnabled) {
                $contextMorphName = config('mandate.column_names.context_morph_name', 'context');
                $contextTypeColumn = $contextMorphName.'_type';
                $contextIdColumn = $contextMorphName.'_id';

                $table->string($contextTypeColumn)->nullable();
                $table->unsignedBigInteger($contextIdColumn)->nullable();

                $table->unique(['name', 'guard', $contextTypeColumn, $contextIdColumn], 'roles_unique');
                $table->index([$contextTypeColumn, $contextIdColumn], 'roles_context_index');
            } else {
                $table->unique(['name', 'guard']);
            }

            $table->timestamps();
            $table->index('guard');
        });
    }

    protected function createPermissionRoleTable(string $idType): void
    {
        $tableName = config('mandate.tables.permission_role', 'permission_role');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');
        $permissionIdColumn = config('mandate.column_names.permission_id', 'permission_id');

        Schema::create($tableName, function (Blueprint $table) use ($rolesTable, $permissionsTable, $roleIdColumn, $permissionIdColumn, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                default => $table->foreignId($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
            };

            match ($idType) {
                'uuid' => $table->foreignUuid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                default => $table->foreignId($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
            };

            $table->primary([$roleIdColumn, $permissionIdColumn]);
        });
    }

    protected function createPermissionSubjectTable(string $idType, bool $contextEnabled): void
    {
        $tableName = config('mandate.tables.permission_subject', 'permission_subject');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $permissionIdColumn = config('mandate.column_names.permission_id', 'permission_id');
        $subjectMorphName = config('mandate.column_names.subject_morph_name', 'subject');
        $subjectIdColumn = $subjectMorphName.'_id';
        $subjectTypeColumn = $subjectMorphName.'_type';

        Schema::create($tableName, function (Blueprint $table) use ($permissionsTable, $permissionIdColumn, $subjectIdColumn, $subjectTypeColumn, $contextEnabled, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                default => $table->foreignId($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
            };

            // Subject morph columns - type depends on user's model ID type, not Mandate's
            $table->string($subjectIdColumn);
            $table->string($subjectTypeColumn);

            if ($contextEnabled) {
                $contextMorphName = config('mandate.column_names.context_morph_name', 'context');
                $contextTypeColumn = $contextMorphName.'_type';
                $contextIdColumn = $contextMorphName.'_id';

                $table->string($contextTypeColumn)->nullable();
                $table->string($contextIdColumn)->nullable();

                $table->primary([$permissionIdColumn, $subjectIdColumn, $subjectTypeColumn, $contextTypeColumn, $contextIdColumn], 'permission_subject_primary');
                $table->index([$contextTypeColumn, $contextIdColumn], 'permission_subject_context_index');
            } else {
                $table->primary([$permissionIdColumn, $subjectIdColumn, $subjectTypeColumn], 'permission_subject_primary');
            }

            $table->timestamps();
            $table->index([$subjectIdColumn, $subjectTypeColumn], 'permission_subject_subject_index');
        });
    }

    protected function createRoleSubjectTable(string $idType, bool $contextEnabled): void
    {
        $tableName = config('mandate.tables.role_subject', 'role_subject');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');
        $subjectMorphName = config('mandate.column_names.subject_morph_name', 'subject');
        $subjectIdColumn = $subjectMorphName.'_id';
        $subjectTypeColumn = $subjectMorphName.'_type';

        Schema::create($tableName, function (Blueprint $table) use ($rolesTable, $roleIdColumn, $subjectIdColumn, $subjectTypeColumn, $contextEnabled, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                default => $table->foreignId($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
            };

            // Subject morph columns - type depends on user's model ID type, not Mandate's
            $table->string($subjectIdColumn);
            $table->string($subjectTypeColumn);

            if ($contextEnabled) {
                $contextMorphName = config('mandate.column_names.context_morph_name', 'context');
                $contextTypeColumn = $contextMorphName.'_type';
                $contextIdColumn = $contextMorphName.'_id';

                $table->string($contextTypeColumn)->nullable();
                $table->string($contextIdColumn)->nullable();

                $table->primary([$roleIdColumn, $subjectIdColumn, $subjectTypeColumn, $contextTypeColumn, $contextIdColumn], 'role_subject_primary');
                $table->index([$contextTypeColumn, $contextIdColumn], 'role_subject_context_index');
            } else {
                $table->primary([$roleIdColumn, $subjectIdColumn, $subjectTypeColumn], 'role_subject_primary');
            }

            $table->timestamps();
            $table->index([$subjectIdColumn, $subjectTypeColumn], 'role_subject_subject_index');
        });
    }
};
