<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\Team;
use OffloadProject\Mandate\Tests\Fixtures\User;

/**
 * The context columns are nullable so a global assignment can be stored. Primary key
 * columns are implicitly NOT NULL on every driver except SQLite, so keeping them out of
 * the key is what makes global assignments possible on Postgres, MySQL and SQL Server.
 *
 * The suite runs on SQLite, which silently permits nulls in a composite primary key, so
 * the schema itself is asserted here rather than relying on a failing insert.
 */
beforeEach(function () {
    $this->user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->team = Team::create(['name' => 'Test Team']);
});

/**
 * Compile the create migration to raw DDL for a driver that is not installed locally.
 *
 * @return array<int, string>
 */
function compileCreateMigrationFor(string $driver, string $table): array
{
    $default = config('database.default');

    config(['database.default' => $driver]);
    Facade::clearResolvedInstance('db.schema');

    try {
        $queries = DB::connection($driver)->pretend(function () {
            $migration = include __DIR__.'/../../database/migrations/2024_01_01_000001_create_mandate_tables.php';
            $migration->up();
        });
    } finally {
        config(['database.default' => $default]);
        Facade::clearResolvedInstance('db.schema');
    }

    return collect($queries)
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, $table))
        ->values()
        ->all();
}

describe('context pivot schema', function () {
    beforeEach(function () {
        $this->enableContext();
    });

    it('keeps the context columns nullable on the role pivot', function () {
        $columns = collect(Schema::getColumns('role_subject'))->keyBy('name');

        expect($columns['context_type']['nullable'])->toBeTrue()
            ->and($columns['context_id']['nullable'])->toBeTrue();
    });

    it('keeps the context columns nullable on the permission pivot', function () {
        $columns = collect(Schema::getColumns('permission_subject'))->keyBy('name');

        expect($columns['context_type']['nullable'])->toBeTrue()
            ->and($columns['context_id']['nullable'])->toBeTrue();
    });

    it('does not include the context columns in a primary key', function (string $table) {
        $primaryKeyColumns = collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => (bool) ($index['primary'] ?? false))
            ->flatMap(fn (array $index): array => $index['columns']);

        expect($primaryKeyColumns)->not->toContain('context_type')
            ->and($primaryKeyColumns)->not->toContain('context_id');
    })->with(['role_subject', 'permission_subject']);

    it('enforces uniqueness with a unique index instead', function (string $table, string $index) {
        expect(Schema::hasIndex($table, $index))->toBeTrue();
    })->with([
        ['role_subject', 'role_subject_unique'],
        ['permission_subject', 'permission_subject_unique'],
    ]);

    it('still uses a composite primary key when context is disabled', function () {
        config(['mandate.context.enabled' => false]);
        $this->recreateTables();

        $primaryKeyColumns = collect(Schema::getIndexes('role_subject'))
            ->filter(fn (array $index): bool => (bool) ($index['primary'] ?? false))
            ->flatMap(fn (array $index): array => $index['columns']);

        expect($primaryKeyColumns->all())->toEqualCanonicalizing(['role_id', 'subject_id', 'subject_type']);
    });

    it('does not put a nullable column in a primary key on other drivers', function (string $driver, string $table) {
        $statements = implode(' ', compileCreateMigrationFor($driver, $table));

        expect($statements)->not->toContain('primary key')
            ->and($statements)->toContain($table.'_unique');
    })->with(['pgsql', 'mysql'])->with(['role_subject', 'permission_subject']);
});

describe('global assignments with context enabled', function () {
    beforeEach(function () {
        $this->enableContext();

        Role::create(['name' => 'admin', 'guard' => 'web']);
        Permission::create(['name' => 'posts:edit', 'guard' => 'web']);
    });

    it('stores a null context for a global role assignment', function () {
        $this->user->assignRole('admin');

        $row = DB::table('role_subject')->first();

        expect($row->context_type)->toBeNull()
            ->and($row->context_id)->toBeNull();
    });

    it('does not duplicate a repeated global role assignment', function () {
        $this->user->assignRole('admin');
        $this->user->assignRole('admin');

        expect(DB::table('role_subject')->whereNull('context_type')->count())->toBe(1);
    });

    it('does not duplicate a repeated global permission grant', function () {
        $this->user->grantPermission('posts:edit');
        $this->user->grantPermission('posts:edit');

        expect(DB::table('permission_subject')->whereNull('context_type')->count())->toBe(1);
    });

    it('keeps global and scoped assignments as separate rows', function () {
        $this->user->assignRole('admin');
        $this->user->assignRole('admin', $this->team);

        expect(DB::table('role_subject')->count())->toBe(2)
            ->and($this->user->hasRole('admin'))->toBeTrue()
            ->and($this->user->hasRole('admin', $this->team))->toBeTrue();
    });

    it('removes only the global assignment', function () {
        $this->user->assignRole('admin');
        $this->user->assignRole('admin', $this->team);

        $this->user->removeRole('admin');

        expect(DB::table('role_subject')->whereNull('context_type')->count())->toBe(0)
            ->and($this->user->hasRole('admin', $this->team))->toBeTrue();
    });
});
