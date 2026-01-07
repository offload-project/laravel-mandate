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
        $tableName = config('mandate.tables.capability_permission', 'capability_permission');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $permissionIdColumn = config('mandate.column_names.permission_id', 'permission_id');

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $permissionsTable, $capabilityIdColumn, $permissionIdColumn) {
            $table->foreignId($capabilityIdColumn)
                ->constrained($capabilitiesTable)
                ->cascadeOnDelete();

            $table->foreignId($permissionIdColumn)
                ->constrained($permissionsTable)
                ->cascadeOnDelete();

            $table->primary([$capabilityIdColumn, $permissionIdColumn]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.capability_permission', 'capability_permission');

        Schema::dropIfExists($tableName);
    }
};
