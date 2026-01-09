<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Concerns;

use Illuminate\Database\Eloquent\Model;
use OffloadProject\Mandate\Contracts\AuditLogger;
use OffloadProject\Mandate\DefaultAuditLogger;

/**
 * Trait providing audit logging functionality for authorization events.
 *
 * This trait centralizes audit logging logic and can be used by HasPermissions
 * and HasRoles traits to log authorization-related events.
 */
trait LogsAuthorization
{
    /**
     * Cached audit logger instance.
     */
    private static ?AuditLogger $auditLogger = null;

    /**
     * Reset the cached audit logger (useful for testing).
     */
    public static function resetAuditLogger(): void
    {
        self::$auditLogger = null;
    }

    /**
     * Check if audit logging is enabled.
     */
    protected function auditLoggingEnabled(): bool
    {
        return config('mandate.audit.enabled', false);
    }

    /**
     * Check if permission/role checks should be logged.
     */
    protected function shouldLogChecks(): bool
    {
        return $this->auditLoggingEnabled() && config('mandate.audit.log_checks', false);
    }

    /**
     * Check if permission/role changes should be logged.
     */
    protected function shouldLogChanges(): bool
    {
        return $this->auditLoggingEnabled() && config('mandate.audit.log_changes', true);
    }

    /**
     * Check if access denials should be logged.
     */
    protected function shouldLogDenials(): bool
    {
        return $this->auditLoggingEnabled() && config('mandate.audit.log_denials', true);
    }

    /**
     * Get the audit logger instance.
     */
    protected function getAuditLogger(): AuditLogger
    {
        if (self::$auditLogger === null) {
            $handlerClass = config('mandate.audit.handler');

            self::$auditLogger = $handlerClass !== null
                ? app($handlerClass)
                : new DefaultAuditLogger;
        }

        return self::$auditLogger;
    }

    /**
     * Log that permissions were granted.
     *
     * @param  array<string>  $permissions
     */
    protected function logPermissionGranted(array $permissions, ?Model $context = null): void
    {
        if (! $this->shouldLogChanges()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logPermissionGranted($this, $permissions, $context);
    }

    /**
     * Log that permissions were revoked.
     *
     * @param  array<string>  $permissions
     */
    protected function logPermissionRevoked(array $permissions, ?Model $context = null): void
    {
        if (! $this->shouldLogChanges()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logPermissionRevoked($this, $permissions, $context);
    }

    /**
     * Log that roles were assigned.
     *
     * @param  array<string>  $roles
     */
    protected function logRoleAssigned(array $roles, ?Model $context = null): void
    {
        if (! $this->shouldLogChanges()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logRoleAssigned($this, $roles, $context);
    }

    /**
     * Log that roles were removed.
     *
     * @param  array<string>  $roles
     */
    protected function logRoleRemoved(array $roles, ?Model $context = null): void
    {
        if (! $this->shouldLogChanges()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logRoleRemoved($this, $roles, $context);
    }

    /**
     * Log a permission check.
     */
    protected function logPermissionCheck(string $permission, bool $result, ?Model $context = null): void
    {
        if (! $this->shouldLogChecks()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logPermissionCheck($this, $permission, $result, $context);

        // Also log denial if check failed and denials should be logged
        if (! $result && $this->shouldLogDenials()) {
            $this->getAuditLogger()->logAccessDenied($this, 'permission', $permission, $context);
        }
    }

    /**
     * Log a role check.
     */
    protected function logRoleCheck(string $role, bool $result, ?Model $context = null): void
    {
        if (! $this->shouldLogChecks()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logRoleCheck($this, $role, $result, $context);

        // Also log denial if check failed and denials should be logged
        if (! $result && $this->shouldLogDenials()) {
            $this->getAuditLogger()->logAccessDenied($this, 'role', $role, $context);
        }
    }

    /**
     * Log an access denial.
     */
    protected function logAccessDenied(string $type, string $name, ?Model $context = null): void
    {
        if (! $this->shouldLogDenials()) {
            return;
        }

        /** @var Model $this */
        $this->getAuditLogger()->logAccessDenied($this, $type, $name, $context);
    }
}
