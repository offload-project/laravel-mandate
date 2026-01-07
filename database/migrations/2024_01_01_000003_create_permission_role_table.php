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
        $tableName = config('mandate.tables.permission_role', 'permission_role');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');
        $permissionIdColumn = config('mandate.column_names.permission_id', 'permission_id');

        Schema::create($tableName, function (Blueprint $table) use ($rolesTable, $permissionsTable, $roleIdColumn, $permissionIdColumn) {
            $table->foreignId($roleIdColumn)
                ->constrained($rolesTable)
                ->cascadeOnDelete();

            $table->foreignId($permissionIdColumn)
                ->constrained($permissionsTable)
                ->cascadeOnDelete();

            $table->primary([$roleIdColumn, $permissionIdColumn]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.permission_role', 'permission_role');

        Schema::dropIfExists($tableName);
    }
};
