<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add label and description columns to Mandate tables for code-first support.
 *
 * These columns are optional and only needed if you want to store
 * human-readable labels and descriptions from your code-first definitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');

        // Add to permissions table
        if (Schema::hasTable($permissionsTable) && ! Schema::hasColumn($permissionsTable, 'label')) {
            Schema::table($permissionsTable, function (Blueprint $table) {
                $table->string('label')->nullable()->after('guard');
                $table->text('description')->nullable()->after('label');
            });
        }

        // Add to roles table
        if (Schema::hasTable($rolesTable) && ! Schema::hasColumn($rolesTable, 'label')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->string('label')->nullable()->after('guard');
                $table->text('description')->nullable()->after('label');
            });
        }

        // Add to capabilities table (if it exists)
        if (Schema::hasTable($capabilitiesTable) && ! Schema::hasColumn($capabilitiesTable, 'label')) {
            Schema::table($capabilitiesTable, function (Blueprint $table) {
                $table->string('label')->nullable()->after('guard');
                $table->text('description')->nullable()->after('label');
            });
        }
    }

    public function down(): void
    {
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $capabilitiesTable = config('mandate.tables.capabilities', 'capabilities');

        // Remove from permissions table
        if (Schema::hasTable($permissionsTable) && Schema::hasColumn($permissionsTable, 'label')) {
            Schema::table($permissionsTable, function (Blueprint $table) {
                $table->dropColumn(['label', 'description']);
            });
        }

        // Remove from roles table
        if (Schema::hasTable($rolesTable) && Schema::hasColumn($rolesTable, 'label')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->dropColumn(['label', 'description']);
            });
        }

        // Remove from capabilities table
        if (Schema::hasTable($capabilitiesTable) && Schema::hasColumn($capabilitiesTable, 'label')) {
            Schema::table($capabilitiesTable, function (Blueprint $table) {
                $table->dropColumn(['label', 'description']);
            });
        }
    }
};
