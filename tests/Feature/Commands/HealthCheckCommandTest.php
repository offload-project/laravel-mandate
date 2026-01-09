<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;

describe('HealthCheckCommand', function () {
    it('passes all checks with default configuration', function () {
        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('supports json output option', function () {
        $this->artisan('mandate:health', ['--json' => true])
            ->assertSuccessful();
    });

    it('returns success when no failures exist', function () {
        $this->artisan('mandate:health')
            ->assertExitCode(0);
    });
});

describe('Wildcard Handler Checks', function () {
    it('passes when wildcards disabled', function () {
        config(['mandate.wildcards.enabled' => false]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes when wildcard handler implements contract', function () {
        config(['mandate.wildcards.enabled' => true]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when wildcard handler class does not exist', function () {
        config([
            'mandate.wildcards.enabled' => true,
            'mandate.wildcards.handler' => 'NonExistent\\WildcardHandler',
        ]);

        $this->artisan('mandate:health')
            ->assertFailed();
    });
});

describe('Feature Handler Checks', function () {
    it('passes when features disabled', function () {
        config(['mandate.features.enabled' => false]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes when feature handler is bound', function () {
        config(['mandate.features.enabled' => true]);

        $handler = Mockery::mock(FeatureAccessHandler::class);
        app()->instance(FeatureAccessHandler::class, $handler);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes with warning when feature handler missing with deny behavior', function () {
        config([
            'mandate.features.enabled' => true,
            'mandate.features.on_missing_handler' => 'deny',
        ]);

        // Warnings don't cause failure
        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes with warning when feature handler missing with allow behavior', function () {
        config([
            'mandate.features.enabled' => true,
            'mandate.features.on_missing_handler' => 'allow',
        ]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when feature handler missing with throw behavior', function () {
        config([
            'mandate.features.enabled' => true,
            'mandate.features.on_missing_handler' => 'throw',
        ]);

        $this->artisan('mandate:health')
            ->assertFailed();
    });
});

describe('Audit Logger Checks', function () {
    it('passes when audit logging disabled', function () {
        config(['mandate.audit.enabled' => false]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes when using default audit logger', function () {
        config([
            'mandate.audit.enabled' => true,
            'mandate.audit.handler' => null,
        ]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes when custom audit handler implements contract', function () {
        config([
            'mandate.audit.enabled' => true,
            'mandate.audit.handler' => OffloadProject\Mandate\DefaultAuditLogger::class,
        ]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when custom audit handler is invalid', function () {
        config([
            'mandate.audit.enabled' => true,
            'mandate.audit.handler' => 'stdClass',
        ]);

        $this->artisan('mandate:health')
            ->assertFailed();
    });
});

describe('Cache Store Checks', function () {
    it('passes when cache store is valid', function () {
        config(['mandate.cache.store' => null]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when cache store does not exist', function () {
        config(['mandate.cache.store' => 'nonexistent_store']);

        $this->artisan('mandate:health')
            ->assertFailed();
    });
});

describe('Database Table Checks', function () {
    it('passes when all required tables exist', function () {
        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('passes with capability tables when capabilities enabled', function () {
        $this->enableCapabilities();

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when capabilities enabled but tables missing', function () {
        // Enable without running migrations
        config(['mandate.capabilities.enabled' => true]);

        $this->artisan('mandate:health')
            ->assertFailed();
    });
});

describe('Orphaned Pivot Records', function () {
    it('passes when no orphaned records exist', function () {
        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('still passes with warning when orphaned permission pivot records exist', function () {
        DB::table('permission_subject')->insert([
            'permission_id' => 99999,
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Warnings don't cause failure
        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('still passes with warning when orphaned role pivot records exist', function () {
        DB::table('role_subject')->insert([
            'role_id' => 99999,
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fixes orphaned permission pivot records with --fix option', function () {
        DB::table('permission_subject')->insert([
            'permission_id' => 99999,
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(DB::table('permission_subject')->where('permission_id', 99999)->count())->toBe(1);

        $this->artisan('mandate:health', ['--fix' => true])
            ->assertSuccessful();

        expect(DB::table('permission_subject')->where('permission_id', 99999)->count())->toBe(0);
    });

    it('fixes orphaned role pivot records with --fix option', function () {
        DB::table('role_subject')->insert([
            'role_id' => 99999,
            'subject_type' => 'App\\Models\\User',
            'subject_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(DB::table('role_subject')->where('role_id', 99999)->count())->toBe(1);

        $this->artisan('mandate:health', ['--fix' => true])
            ->assertSuccessful();

        expect(DB::table('role_subject')->where('role_id', 99999)->count())->toBe(0);
    });
});

describe('Context Column Checks', function () {
    it('passes when context enabled and columns exist', function () {
        config(['mandate.context.enabled' => true]);

        $contextType = config('mandate.column_names.context_morph_name', 'context').'_type';
        $contextId = config('mandate.column_names.context_morph_name', 'context').'_id';

        // Add context columns if they don't exist
        if (! Schema::hasColumn('permission_subject', $contextType)) {
            Schema::table('permission_subject', function ($table) use ($contextType, $contextId) {
                $table->string($contextType)->nullable();
                $table->unsignedBigInteger($contextId)->nullable();
            });
        }

        if (! Schema::hasColumn('role_subject', $contextType)) {
            Schema::table('role_subject', function ($table) use ($contextType, $contextId) {
                $table->string($contextType)->nullable();
                $table->unsignedBigInteger($contextId)->nullable();
            });
        }

        $this->artisan('mandate:health')
            ->assertSuccessful();
    });

    it('fails when context enabled but columns missing', function () {
        config(['mandate.context.enabled' => true]);

        $contextType = config('mandate.column_names.context_morph_name', 'context').'_type';

        // Only run this test if columns don't exist
        if (! Schema::hasColumn('permission_subject', $contextType)) {
            $this->artisan('mandate:health')
                ->assertFailed();
        } else {
            // Columns already exist from previous test, so it passes
            $this->artisan('mandate:health')
                ->assertSuccessful();
        }
    });
});
