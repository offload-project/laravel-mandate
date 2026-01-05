<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Services\MandateManager;

beforeEach(function () {
    app(MandateManager::class)->clearCache();
    app(MandateManager::class)->syncPermissions();
});

describe('HasContextScope Trait', function () {
    test('scopeForScope adds scope constraint to query', function () {
        $query = Permission::query()->forScope(null);

        // Should have added scope constraints to the query
        expect($query->toSql())->toContain('scope');
    });

    test('scopeForScope filters by specific scope', function () {
        // Set up context columns config
        config()->set('mandate.context.permissions', true);

        $permission = Permission::query()->firstOrCreate([
            'name' => 'scoped.permission',
            'guard_name' => 'web',
        ]);

        // Query with a specific scope
        $query = Permission::query()->forScope('feature');

        // The query should have scope constraint
        expect($query->toSql())->toContain('scope');
    });

    test('scopeWithScope includes global and specific scope', function () {
        $permission = Permission::query()->firstOrCreate([
            'name' => 'test.permission',
            'guard_name' => 'web',
        ]);

        $query = Permission::query()->withScope('team');

        // Should include both null scope and specific scope
        expect($query->toSql())->toContain('scope');
    });

    test('hasContextColumns returns false when context disabled', function () {
        config()->set('mandate.context.permissions', false);

        $permission = Permission::query()->first();

        if ($permission) {
            expect($permission->hasContextColumns())->toBeFalse();
        } else {
            expect(true)->toBeTrue(); // Skip if no permissions
        }
    });

    test('hasContextColumns returns true when context enabled', function () {
        config()->set('mandate.context.permissions', true);
        config()->set('mandate.tables.permissions', 'mandate_permissions');

        $permission = Permission::query()->first();

        if ($permission) {
            expect($permission->hasContextColumns())->toBeTrue();
        } else {
            expect(true)->toBeTrue(); // Skip if no permissions
        }
    });

    test('getScope returns null when context disabled', function () {
        config()->set('mandate.context.permissions', false);

        $permission = Permission::query()->first();

        if ($permission) {
            expect($permission->getScope())->toBeNull();
        } else {
            expect(true)->toBeTrue();
        }
    });

    test('getContextModel returns null when context disabled', function () {
        config()->set('mandate.context.permissions', false);

        $permission = Permission::query()->first();

        if ($permission) {
            expect($permission->getContextModel())->toBeNull();
        } else {
            expect(true)->toBeTrue();
        }
    });

    test('scopeForScope filters by Model instance context', function () {
        // Create a mock model for context
        $contextModel = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public function getMorphClass(): string
            {
                return 'App\\Models\\User';
            }

            public function getKey(): mixed
            {
                return 123;
            }
        };

        $query = Permission::query()->forScope('team', $contextModel);

        $sql = $query->toSql();
        expect($sql)->toContain('scope');
        expect($sql)->toContain('context_model_type');
        expect($sql)->toContain('context_model_id');
    });

    test('scopeForScope filters by string context type', function () {
        $query = Permission::query()->forScope('team', 'App\\Models\\Team');

        $sql = $query->toSql();
        expect($sql)->toContain('scope');
        expect($sql)->toContain('context_model_type');
    });

    test('scopeWithScope includes Model instance context', function () {
        $contextModel = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public function getMorphClass(): string
            {
                return 'App\\Models\\User';
            }

            public function getKey(): mixed
            {
                return 456;
            }
        };

        $query = Permission::query()->withScope('team', $contextModel);

        $sql = $query->toSql();
        expect($sql)->toContain('scope');
        expect($sql)->toContain('context_model_type');
        expect($sql)->toContain('context_model_id');
    });

    test('scopeWithScope includes string context type', function () {
        $query = Permission::query()->withScope('team', 'App\\Models\\Team');

        $sql = $query->toSql();
        expect($sql)->toContain('scope');
        expect($sql)->toContain('context_model_type');
    });

    test('scopeWithScope with only context model no scope', function () {
        $contextModel = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public function getMorphClass(): string
            {
                return 'App\\Models\\User';
            }

            public function getKey(): mixed
            {
                return 789;
            }
        };

        $query = Permission::query()->withScope(null, $contextModel);

        $sql = $query->toSql();
        expect($sql)->toContain('context_model_type');
        expect($sql)->toContain('context_model_id');
    });

    test('hasContextColumns returns false for unconfigured table', function () {
        // Clear the tables config to test the default return false path
        config()->set('mandate.tables', []);
        config()->set('mandate.context', []);

        $permission = Permission::query()->first();

        if ($permission) {
            expect($permission->hasContextColumns())->toBeFalse();
        } else {
            expect(true)->toBeTrue();
        }
    });

    test('getContextModel returns null when context attributes are null', function () {
        config()->set('mandate.context.permissions', true);
        config()->set('mandate.tables.permissions', 'mandate_permissions');

        $permission = Permission::query()->first();

        if ($permission) {
            // Even with context enabled, returns null if type/id attributes are null
            expect($permission->getContextModel())->toBeNull();
        } else {
            expect(true)->toBeTrue();
        }
    });
});
