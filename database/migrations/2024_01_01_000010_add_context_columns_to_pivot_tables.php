<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run if context is enabled
        if (! config('mandate.context.enabled', false)) {
            return;
        }

        $contextType = config('mandate.column_names.context_morph_type', 'context_type');
        $contextId = config('mandate.column_names.context_morph_key', 'context_id');
        $permissionId = config('mandate.column_names.permission_id', 'permission_id');
        $roleId = config('mandate.column_names.role_id', 'role_id');
        $subjectId = config('mandate.column_names.subject_morph_key', 'subject_id');
        $subjectType = config('mandate.column_names.subject_morph_type', 'subject_type');

        $permissionSubjectTable = config('mandate.tables.permission_subject', 'permission_subject');
        $roleSubjectTable = config('mandate.tables.role_subject', 'role_subject');
        $permissionRoleTable = config('mandate.tables.permission_role', 'permission_role');

        // Check if migration has already been run (idempotent)
        if (Schema::hasColumn($permissionSubjectTable, $contextType)) {
            return;
        }

        // For SQLite, we need to recreate tables to change primary keys
        // For other databases, we can alter them
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->migrateSqlite(
                $permissionSubjectTable,
                $roleSubjectTable,
                $permissionRoleTable,
                $contextType,
                $contextId,
                $permissionId,
                $roleId,
                $subjectId,
                $subjectType
            );
        } else {
            $this->migrateOther(
                $permissionSubjectTable,
                $roleSubjectTable,
                $permissionRoleTable,
                $contextType,
                $contextId,
                $permissionId,
                $roleId,
                $subjectId,
                $subjectType
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! config('mandate.context.enabled', false)) {
            return;
        }

        $contextType = config('mandate.column_names.context_morph_type', 'context_type');
        $contextId = config('mandate.column_names.context_morph_key', 'context_id');

        Schema::table(config('mandate.tables.permission_subject', 'permission_subject'), function (Blueprint $table) use ($contextType, $contextId) {
            $table->dropIndex('permission_subject_context_index');
            $table->dropColumn([$contextType, $contextId]);
        });

        Schema::table(config('mandate.tables.role_subject', 'role_subject'), function (Blueprint $table) use ($contextType, $contextId) {
            $table->dropIndex('role_subject_context_index');
            $table->dropColumn([$contextType, $contextId]);
        });

        Schema::table(config('mandate.tables.permission_role', 'permission_role'), function (Blueprint $table) use ($contextType, $contextId) {
            $table->dropIndex('permission_role_context_index');
            $table->dropColumn([$contextType, $contextId]);
        });
    }

    private function migrateSqlite(
        string $permissionSubjectTable,
        string $roleSubjectTable,
        string $permissionRoleTable,
        string $contextType,
        string $contextId,
        string $permissionId,
        string $roleId,
        string $subjectId,
        string $subjectType
    ): void {
        // In SQLite, indexes have global names. We need to drop old indexes before recreating tables.

        // Migrate permission_subject table
        DB::statement('DROP INDEX IF EXISTS permission_subject_subject_index');
        DB::statement('DROP INDEX IF EXISTS permission_subject_primary');

        Schema::rename($permissionSubjectTable, $permissionSubjectTable.'_old');
        Schema::create($permissionSubjectTable, function (Blueprint $table) use ($permissionId, $subjectId, $subjectType, $contextType, $contextId) {
            $table->unsignedBigInteger($permissionId);
            $table->unsignedBigInteger($subjectId);
            $table->string($subjectType);
            $table->string($contextType)->nullable();
            $table->unsignedBigInteger($contextId)->nullable();
            $table->timestamps();

            $table->index([$subjectId, $subjectType], 'permission_subject_subject_index');
            $table->index([$contextType, $contextId], 'permission_subject_context_index');
        });
        DB::statement("INSERT INTO {$permissionSubjectTable} ({$permissionId}, {$subjectId}, {$subjectType}, created_at, updated_at)
            SELECT {$permissionId}, {$subjectId}, {$subjectType}, created_at, updated_at FROM {$permissionSubjectTable}_old");
        Schema::drop($permissionSubjectTable.'_old');

        // Add unique constraint
        DB::statement("CREATE UNIQUE INDEX permission_subject_unique ON {$permissionSubjectTable} ({$permissionId}, {$subjectId}, {$subjectType}, COALESCE({$contextType}, ''), COALESCE({$contextId}, 0))");

        // Migrate role_subject table
        DB::statement('DROP INDEX IF EXISTS role_subject_subject_index');
        DB::statement('DROP INDEX IF EXISTS role_subject_primary');

        Schema::rename($roleSubjectTable, $roleSubjectTable.'_old');
        Schema::create($roleSubjectTable, function (Blueprint $table) use ($roleId, $subjectId, $subjectType, $contextType, $contextId) {
            $table->unsignedBigInteger($roleId);
            $table->unsignedBigInteger($subjectId);
            $table->string($subjectType);
            $table->string($contextType)->nullable();
            $table->unsignedBigInteger($contextId)->nullable();
            $table->timestamps();

            $table->index([$subjectId, $subjectType], 'role_subject_subject_index');
            $table->index([$contextType, $contextId], 'role_subject_context_index');
        });
        DB::statement("INSERT INTO {$roleSubjectTable} ({$roleId}, {$subjectId}, {$subjectType}, created_at, updated_at)
            SELECT {$roleId}, {$subjectId}, {$subjectType}, created_at, updated_at FROM {$roleSubjectTable}_old");
        Schema::drop($roleSubjectTable.'_old');

        DB::statement("CREATE UNIQUE INDEX role_subject_unique ON {$roleSubjectTable} ({$roleId}, {$subjectId}, {$subjectType}, COALESCE({$contextType}, ''), COALESCE({$contextId}, 0))");

        // Migrate permission_role table (doesn't have timestamps)
        DB::statement('DROP INDEX IF EXISTS permission_role_primary');

        Schema::rename($permissionRoleTable, $permissionRoleTable.'_old');
        Schema::create($permissionRoleTable, function (Blueprint $table) use ($permissionId, $roleId, $contextType, $contextId) {
            $table->unsignedBigInteger($permissionId);
            $table->unsignedBigInteger($roleId);
            $table->string($contextType)->nullable();
            $table->unsignedBigInteger($contextId)->nullable();

            $table->index([$contextType, $contextId], 'permission_role_context_index');
        });
        DB::statement("INSERT INTO {$permissionRoleTable} ({$permissionId}, {$roleId})
            SELECT {$permissionId}, {$roleId} FROM {$permissionRoleTable}_old");
        Schema::drop($permissionRoleTable.'_old');

        DB::statement("CREATE UNIQUE INDEX permission_role_unique ON {$permissionRoleTable} ({$permissionId}, {$roleId}, COALESCE({$contextType}, ''), COALESCE({$contextId}, 0))");
    }

    private function migrateOther(
        string $permissionSubjectTable,
        string $roleSubjectTable,
        string $permissionRoleTable,
        string $contextType,
        string $contextId,
        string $permissionId,
        string $roleId,
        string $subjectId,
        string $subjectType
    ): void {
        // Add context columns and update primary keys for other databases

        // permission_subject table
        Schema::table($permissionSubjectTable, function (Blueprint $table) use ($contextType, $contextId, $permissionId, $subjectId, $subjectType) {
            $table->dropPrimary('permission_subject_primary');
            $table->string($contextType)->nullable()->after($subjectType);
            $table->unsignedBigInteger($contextId)->nullable()->after($contextType);
            $table->primary([$permissionId, $subjectId, $subjectType, $contextType, $contextId], 'permission_subject_primary');
            $table->index([$contextType, $contextId], 'permission_subject_context_index');
        });

        // role_subject table
        Schema::table($roleSubjectTable, function (Blueprint $table) use ($contextType, $contextId, $roleId, $subjectId, $subjectType) {
            $table->dropPrimary('role_subject_primary');
            $table->string($contextType)->nullable()->after($subjectType);
            $table->unsignedBigInteger($contextId)->nullable()->after($contextType);
            $table->primary([$roleId, $subjectId, $subjectType, $contextType, $contextId], 'role_subject_primary');
            $table->index([$contextType, $contextId], 'role_subject_context_index');
        });

        // permission_role table
        Schema::table($permissionRoleTable, function (Blueprint $table) use ($contextType, $contextId, $permissionId, $roleId) {
            $table->dropPrimary('permission_role_primary');
            $table->string($contextType)->nullable()->after($roleId);
            $table->unsignedBigInteger($contextId)->nullable()->after($contextType);
            $table->primary([$permissionId, $roleId, $contextType, $contextId], 'permission_role_primary');
            $table->index([$contextType, $contextId], 'permission_role_context_index');
        });
    }
};
