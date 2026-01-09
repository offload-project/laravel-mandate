<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OffloadProject\Mandate\Contracts\AuditLogger;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Contracts\WildcardHandler;
use OffloadProject\Mandate\MandateRegistrar;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Command to check Mandate configuration and database health.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'mandate:health
        {--fix : Attempt to fix common issues}
        {--json : Output results as JSON}';

    protected $description = 'Check Mandate configuration and database health';

    /** @var array<string, array{status: string, message: string}> */
    private array $checks = [];

    public function handle(): int
    {
        $this->runConfigurationChecks();
        $this->runDatabaseChecks();
        $this->runIntegrityChecks();

        if ($this->option('json')) {
            $json = json_encode($this->checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $this->line($json);

            return $this->hasFailures() ? self::FAILURE : self::SUCCESS;
        }

        $this->displayResults();

        return $this->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function runConfigurationChecks(): void
    {
        // Check model classes exist
        $this->checkModelClass('permission', config('mandate.models.permission'));
        $this->checkModelClass('role', config('mandate.models.role'));

        if (config('mandate.capabilities.enabled')) {
            $this->checkModelClass('capability', config('mandate.models.capability'));
        }

        // Check wildcard handler
        if (config('mandate.wildcards.enabled')) {
            $handler = config('mandate.wildcards.handler');
            if ($handler && class_exists($handler)) {
                if (in_array(WildcardHandler::class, class_implements($handler) ?: [])) {
                    $this->addPass('wildcard_handler', 'Wildcard handler implements contract');
                } else {
                    $this->addFail('wildcard_handler', "Handler {$handler} does not implement WildcardHandler");
                }
            } else {
                $this->addFail('wildcard_handler', "Wildcard handler class not found: {$handler}");
            }
        } else {
            $this->addSkip('wildcard_handler', 'Wildcards disabled');
        }

        // Check feature handler
        if (config('mandate.features.enabled')) {
            if (app()->bound(FeatureAccessHandler::class)) {
                $this->addPass('feature_handler', 'Feature access handler is bound');
            } else {
                $behavior = config('mandate.features.on_missing_handler', 'deny');
                if ($behavior === 'allow') {
                    $this->addWarn('feature_handler', 'No feature handler bound (will allow access)');
                } elseif ($behavior === 'throw') {
                    $this->addFail('feature_handler', 'No feature handler bound (will throw exceptions)');
                } else {
                    $this->addWarn('feature_handler', 'No feature handler bound (will deny access)');
                }
            }
        } else {
            $this->addSkip('feature_handler', 'Feature integration disabled');
        }

        // Check audit logger
        if (config('mandate.audit.enabled')) {
            $handler = config('mandate.audit.handler');
            if ($handler) {
                if (class_exists($handler) && in_array(AuditLogger::class, class_implements($handler) ?: [])) {
                    $this->addPass('audit_handler', 'Custom audit handler implements contract');
                } else {
                    $this->addFail('audit_handler', "Audit handler {$handler} is invalid");
                }
            } else {
                $this->addPass('audit_handler', 'Using default audit logger');
            }
        } else {
            $this->addSkip('audit_handler', 'Audit logging disabled');
        }

        // Check cache configuration
        $cacheStore = config('mandate.cache.store');
        if ($cacheStore && ! config("cache.stores.{$cacheStore}")) {
            $this->addFail('cache_store', "Cache store '{$cacheStore}' not configured");
        } else {
            $this->addPass('cache_store', 'Cache store configured correctly');
        }
    }

    private function runDatabaseChecks(): void
    {
        $tables = [
            'permissions' => config('mandate.tables.permissions', 'permissions'),
            'roles' => config('mandate.tables.roles', 'roles'),
            'permission_role' => config('mandate.tables.permission_role', 'permission_role'),
            'permission_subject' => config('mandate.tables.permission_subject', 'permission_subject'),
            'role_subject' => config('mandate.tables.role_subject', 'role_subject'),
        ];

        if (config('mandate.capabilities.enabled')) {
            $tables['capabilities'] = config('mandate.tables.capabilities', 'capabilities');
            $tables['capability_permission'] = config('mandate.tables.capability_permission', 'capability_permission');
            $tables['capability_role'] = config('mandate.tables.capability_role', 'capability_role');

            if (config('mandate.capabilities.direct_assignment')) {
                $tables['capability_subject'] = config('mandate.tables.capability_subject', 'capability_subject');
            }
        }

        foreach ($tables as $key => $table) {
            if (Schema::hasTable($table)) {
                $this->addPass("table_{$key}", "Table '{$table}' exists");
            } else {
                $this->addFail("table_{$key}", "Table '{$table}' missing - run migrations");
            }
        }

        // Check context columns if enabled
        if (config('mandate.context.enabled')) {
            $contextType = config('mandate.column_names.context_morph_name', 'context').'_type';
            $contextId = config('mandate.column_names.context_morph_name', 'context').'_id';

            $pivotTables = [
                config('mandate.tables.permission_subject', 'permission_subject'),
                config('mandate.tables.role_subject', 'role_subject'),
            ];

            foreach ($pivotTables as $table) {
                if (Schema::hasTable($table)) {
                    if (Schema::hasColumns($table, [$contextType, $contextId])) {
                        $this->addPass("context_{$table}", "Context columns exist in '{$table}'");
                    } else {
                        $this->addFail("context_{$table}", "Context columns missing in '{$table}'");
                    }
                }
            }
        }
    }

    private function runIntegrityChecks(): void
    {
        // Check for orphaned pivot records
        $permissionSubject = config('mandate.tables.permission_subject', 'permission_subject');
        $permissionsTable = config('mandate.tables.permissions', 'permissions');
        $permissionId = config('mandate.column_names.permission_id', 'permission_id');

        if (Schema::hasTable($permissionSubject) && Schema::hasTable($permissionsTable)) {
            $orphanedPermissions = DB::table($permissionSubject)
                ->whereNotIn($permissionId, DB::table($permissionsTable)->select('id'))
                ->count();

            if ($orphanedPermissions > 0) {
                $this->addWarn('orphaned_permission_pivots', "{$orphanedPermissions} orphaned permission pivot records");

                if ($this->option('fix')) {
                    DB::table($permissionSubject)
                        ->whereNotIn($permissionId, DB::table($permissionsTable)->select('id'))
                        ->delete();
                    $this->addPass('orphaned_permission_pivots', "Deleted {$orphanedPermissions} orphaned records");
                }
            } else {
                $this->addPass('orphaned_permission_pivots', 'No orphaned permission pivot records');
            }
        }

        $roleSubject = config('mandate.tables.role_subject', 'role_subject');
        $rolesTable = config('mandate.tables.roles', 'roles');
        $roleId = config('mandate.column_names.role_id', 'role_id');

        if (Schema::hasTable($roleSubject) && Schema::hasTable($rolesTable)) {
            $orphanedRoles = DB::table($roleSubject)
                ->whereNotIn($roleId, DB::table($rolesTable)->select('id'))
                ->count();

            if ($orphanedRoles > 0) {
                $this->addWarn('orphaned_role_pivots', "{$orphanedRoles} orphaned role pivot records");

                if ($this->option('fix')) {
                    DB::table($roleSubject)
                        ->whereNotIn($roleId, DB::table($rolesTable)->select('id'))
                        ->delete();
                    $this->addPass('orphaned_role_pivots', "Deleted {$orphanedRoles} orphaned records");
                }
            } else {
                $this->addPass('orphaned_role_pivots', 'No orphaned role pivot records');
            }
        }

        // Check cache is working
        try {
            $registrar = app(MandateRegistrar::class);
            $registrar->getPermissions();
            $this->addPass('cache_working', 'Permission cache is working');
        } catch (Exception $e) {
            $this->addFail('cache_working', 'Cache error: '.$e->getMessage());
        }
    }

    /**
     * @param  class-string|null  $class
     */
    private function checkModelClass(string $type, ?string $class): void
    {
        if (! $class) {
            $this->addFail("{$type}_model", ucfirst($type).' model not configured');

            return;
        }

        if (! class_exists($class)) {
            $this->addFail("{$type}_model", "Class {$class} does not exist");

            return;
        }

        $this->addPass("{$type}_model", ucfirst($type)." model: {$class}");
    }

    private function addPass(string $check, string $message): void
    {
        $this->checks[$check] = ['status' => 'pass', 'message' => $message];
    }

    private function addFail(string $check, string $message): void
    {
        $this->checks[$check] = ['status' => 'fail', 'message' => $message];
    }

    private function addWarn(string $check, string $message): void
    {
        $this->checks[$check] = ['status' => 'warn', 'message' => $message];
    }

    private function addSkip(string $check, string $message): void
    {
        $this->checks[$check] = ['status' => 'skip', 'message' => $message];
    }

    private function hasFailures(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'fail') {
                return true;
            }
        }

        return false;
    }

    private function displayResults(): void
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $skipped = 0;

        $rows = [];
        foreach ($this->checks as $name => $check) {
            $icon = match ($check['status']) {
                'pass' => '✓',
                'fail' => '✗',
                'warn' => '⚠',
                'skip' => '○',
                default => '?',
            };

            $rows[] = [$icon, $name, $check['message']];

            switch ($check['status']) {
                case 'pass':
                    $passed++;
                    break;
                case 'fail':
                    $failed++;
                    break;
                case 'warn':
                    $warnings++;
                    break;
                case 'skip':
                    $skipped++;
                    break;
            }
        }

        table(['', 'Check', 'Message'], $rows);

        $this->newLine();

        if ($failed > 0) {
            warning("Health check completed: {$passed} passed, {$failed} failed, {$warnings} warnings, {$skipped} skipped");
        } else {
            info("Health check completed: {$passed} passed, {$warnings} warnings, {$skipped} skipped");
        }
    }
}
