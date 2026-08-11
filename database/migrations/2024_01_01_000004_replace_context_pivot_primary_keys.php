<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the composite primary key on the context-aware pivot tables with a unique index.
 *
 * When context support is enabled, `permission_subject` and `role_subject` originally
 * carried a primary key that included the nullable context columns. Primary key columns
 * are implicitly NOT NULL, so the database rejected global (null context) assignments:
 *
 *     null value in column "context_type" violates not-null constraint
 *
 * This migration is only needed by installations that ran the original migration with
 * context enabled. It is a no-op everywhere else.
 *
 * SQLite is skipped: it does not apply the implicit NOT NULL to primary key columns, so
 * those installations were never affected, and its primary key cannot be dropped in place.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! config('mandate.context.enabled', false)) {
            return;
        }

        $morphIdType = config('mandate.morph_id_type') ?? config('mandate.model_id_type', 'int');

        $this->replacePrimaryKey(
            config('mandate.tables.permission_subject', 'permission_subject'),
            config('mandate.column_names.permission_id', 'permission_id'),
            'permission_subject',
            $morphIdType,
        );

        $this->replacePrimaryKey(
            config('mandate.tables.role_subject', 'role_subject'),
            config('mandate.column_names.role_id', 'role_id'),
            'role_subject',
            $morphIdType,
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! config('mandate.context.enabled', false)) {
            return;
        }

        $morphIdType = config('mandate.morph_id_type') ?? config('mandate.model_id_type', 'int');

        $this->restorePrimaryKey(
            config('mandate.tables.permission_subject', 'permission_subject'),
            config('mandate.column_names.permission_id', 'permission_id'),
            'permission_subject',
            $morphIdType,
        );

        $this->restorePrimaryKey(
            config('mandate.tables.role_subject', 'role_subject'),
            config('mandate.column_names.role_id', 'role_id'),
            'role_subject',
            $morphIdType,
        );
    }

    /**
     * Swap the composite primary key for a unique index so the context columns stay nullable.
     */
    protected function replacePrimaryKey(string $table, string $ownerIdColumn, string $indexPrefix, string $morphIdType): void
    {
        if (! $this->isAffected($table)) {
            return;
        }

        [$contextTypeColumn, $contextIdColumn] = $this->contextColumns();
        $uniqueIndex = $indexPrefix.'_unique';

        // Created before the primary key is dropped so MySQL always has an index covering
        // the foreign key column.
        if (! Schema::hasIndex($table, $uniqueIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($ownerIdColumn, $uniqueIndex) {
                $blueprint->unique($this->keyColumns($ownerIdColumn), $uniqueIndex);
            });
        }

        if ($this->hasPrimaryKey($table)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexPrefix) {
                $blueprint->dropPrimary($indexPrefix.'_primary');
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($contextTypeColumn, $contextIdColumn, $morphIdType) {
            $blueprint->string($contextTypeColumn)->nullable()->change();
            $this->morphIdColumn($blueprint, $contextIdColumn, $morphIdType)->nullable()->change();
        });
    }

    /**
     * Restore the composite primary key, provided no global assignments exist.
     */
    protected function restorePrimaryKey(string $table, string $ownerIdColumn, string $indexPrefix, string $morphIdType): void
    {
        if (! $this->isAffected($table)) {
            return;
        }

        [$contextTypeColumn, $contextIdColumn] = $this->contextColumns();

        $globalAssignments = DB::table($table)
            ->whereNull($contextTypeColumn)
            ->orWhereNull($contextIdColumn)
            ->count();

        if ($globalAssignments > 0) {
            throw new RuntimeException(
                "Cannot restore the composite primary key on '{$table}': {$globalAssignments} global "
                .'assignment(s) have a null context, which a primary key cannot store. Remove them first.'
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($contextTypeColumn, $contextIdColumn, $morphIdType) {
            $blueprint->string($contextTypeColumn)->nullable(false)->change();
            $this->morphIdColumn($blueprint, $contextIdColumn, $morphIdType)->nullable(false)->change();
        });

        if (! $this->hasPrimaryKey($table)) {
            Schema::table($table, function (Blueprint $blueprint) use ($ownerIdColumn, $indexPrefix) {
                $blueprint->primary($this->keyColumns($ownerIdColumn), $indexPrefix.'_primary');
            });
        }

        $uniqueIndex = $indexPrefix.'_unique';

        if (Schema::hasIndex($table, $uniqueIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($uniqueIndex) {
                $blueprint->dropUnique($uniqueIndex);
            });
        }
    }

    /**
     * Determine whether the table exists on a driver that enforces NOT NULL on key columns.
     */
    protected function isAffected(string $table): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        return Schema::hasTable($table)
            && Schema::hasColumns($table, $this->contextColumns());
    }

    /**
     * Get the columns the key is built from.
     *
     * @return array<int, string>
     */
    protected function keyColumns(string $ownerIdColumn): array
    {
        $subjectMorphName = config('mandate.column_names.subject_morph_name', 'subject');

        return [
            $ownerIdColumn,
            $subjectMorphName.'_id',
            $subjectMorphName.'_type',
            ...$this->contextColumns(),
        ];
    }

    /**
     * Get the context type and id column names.
     *
     * @return array{0: string, 1: string}
     */
    protected function contextColumns(): array
    {
        $contextMorphName = config('mandate.column_names.context_morph_name', 'context');

        return [$contextMorphName.'_type', $contextMorphName.'_id'];
    }

    /**
     * Determine whether the table still has a primary key.
     */
    protected function hasPrimaryKey(string $table): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] ?? false) {
                return true;
            }
        }

        return false;
    }

    protected function morphIdColumn(Blueprint $table, string $column, string $idType): Illuminate\Database\Schema\ColumnDefinition
    {
        return match ($idType) {
            'uuid' => $table->uuid($column),
            'ulid' => $table->ulid($column),
            default => $table->unsignedBigInteger($column),
        };
    }
};
