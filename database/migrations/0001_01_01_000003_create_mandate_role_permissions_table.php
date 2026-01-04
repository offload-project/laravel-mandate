<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('mandate.tables.role_permissions', 'mandate_role_permissions');
        $rolesTable = config('mandate.tables.roles', 'mandate_roles');
        $permissionsTable = config('mandate.tables.permissions', 'mandate_permissions');
        $idType = config('mandate.id_type', 'bigint');
        $roleKey = config('mandate.columns.pivot_role_key', 'role_id');
        $permissionKey = config('mandate.columns.pivot_permission_key', 'permission_id');

        Schema::create($tableName, function (Blueprint $table) use ($idType, $roleKey, $permissionKey, $rolesTable, $permissionsTable) {
            if ($idType === 'uuid') {
                $table->uuid($roleKey);
                $table->uuid($permissionKey);
            } else {
                $table->unsignedBigInteger($roleKey);
                $table->unsignedBigInteger($permissionKey);
            }

            $table->foreign($roleKey)
                ->references('id')
                ->on($rolesTable)
                ->cascadeOnDelete();

            $table->foreign($permissionKey)
                ->references('id')
                ->on($permissionsTable)
                ->cascadeOnDelete();

            $table->primary([$roleKey, $permissionKey]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('mandate.tables.role_permissions', 'mandate_role_permissions'));
    }
};
