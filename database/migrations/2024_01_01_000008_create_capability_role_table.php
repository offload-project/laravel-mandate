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
        $tableName = config('mandate.tables.capability_role', 'capability_role');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $capabilityIdColumn = config('mandate.column_names.capability_id', 'capability_id');
        $roleIdColumn = config('mandate.column_names.role_id', 'role_id');

        Schema::create($tableName, function (Blueprint $table) use ($capabilitiesTable, $rolesTable, $capabilityIdColumn, $roleIdColumn) {
            $table->foreignId($capabilityIdColumn)
                ->constrained($capabilitiesTable)
                ->cascadeOnDelete();

            $table->foreignId($roleIdColumn)
                ->constrained($rolesTable)
                ->cascadeOnDelete();

            $table->primary([$capabilityIdColumn, $roleIdColumn]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('mandate.tables.capability_role', 'capability_role');

        Schema::dropIfExists($tableName);
    }
};
