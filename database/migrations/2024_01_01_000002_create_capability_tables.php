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

        $this->createCapabilitiesTable($idType);
        $this->createCapabilityPermissionTable($idType);
        $this->createCapabilityRoleTable($idType);
        $this->createCapabilitySubjectTable($idType);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.capability_subject', 'capability_subject'));
        Schema::dropIfExists(config('mandate.tables.capability_role', 'capability_role'));
        Schema::dropIfExists(config('mandate.tables.capability_permission', 'capability_permission'));
        Schema::dropIfExists(config('mandate.tables.capabilities', 'capabilities'));
    }

    protected function createCapabilitiesTable(string $idType): void
    {
        $tableName = config('mandate.tables.capabilities', 'capabilities');

        Schema::create($tableName, function (Blueprint $table) use ($idType) {
            match ($idType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };

            $table->string('name');
            $table->string('guard');
            $table->timestamps();

            $table->unique(['name', 'guard']);
            $table->index('guard');
        });
    }

    protected function createCapabilityPermissionTable(string $idType): void
    {
        $tableName = config('mandate.tables.capability_permission', 'capability_permission');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $permissionIdColumn = config('mandate.column_names.permission_id', 'permission_id');

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $permissionsTable, $capabilityIdColumn, $permissionIdColumn, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                default => $table->foreignId($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
            };

            match ($idType) {
                'uuid' => $table->foreignUuid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
                default => $table->foreignId($permissionIdColumn)->constrained($permissionsTable)->cascadeOnDelete(),
            };

            $table->primary([$capabilityIdColumn, $permissionIdColumn]);
        });
    }

    protected function createCapabilityRoleTable(string $idType): void
    {
        $tableName = config('mandate.tables.capability_role', 'capability_role');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $rolesTable, $capabilityIdColumn, $roleIdColumn, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                default => $table->foreignId($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
            };

            match ($idType) {
                'uuid' => $table->foreignUuid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
                default => $table->foreignId($roleIdColumn)->constrained($rolesTable)->cascadeOnDelete(),
            };

            $table->primary([$capabilityIdColumn, $roleIdColumn]);
        });
    }

    protected function createCapabilitySubjectTable(string $idType): void
    {
        $tableName = config('mandate.tables.capability_subject', 'capability_subject');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $subjectMorphName = config('mandate.column_names.subject_morph_name', 'subject');
        $subjectIdColumn = $subjectMorphName.'_id';
        $subjectTypeColumn = $subjectMorphName.'_type';

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $capabilityIdColumn, $subjectIdColumn, $subjectTypeColumn, $idType) {
            match ($idType) {
                'uuid' => $table->foreignUuid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                'ulid' => $table->foreignUlid($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
                default => $table->foreignId($capabilityIdColumn)->constrained($capabilitiesTable)->cascadeOnDelete(),
            };

            // Subject morph columns - type depends on user's model ID type, not Mandate's
            $table->string($subjectIdColumn);
            $table->string($subjectTypeColumn);

            $table->timestamps();

            $table->primary([$capabilityIdColumn, $subjectIdColumn, $subjectTypeColumn], 'capability_subject_primary');
            $table->index([$subjectIdColumn, $subjectTypeColumn], 'capability_subject_subject_index');
        });
    }
};
