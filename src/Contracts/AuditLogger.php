<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for custom audit logging implementations.
 *
 * Implement this interface to provide custom audit logging for Mandate
 * authorization events. This allows integration with external audit
 * systems, custom log formats, or database-backed audit trails.
 *
 * @example
 * ```php
 * class DatabaseAuditLogger implements AuditLogger
 * {
 *     public function logPermissionGranted(Model $subject, array $permissions, ?Model $context = null): void
 *     {
 *         AuditLog::create([
 *             'action' => 'permission_granted',
 *             'subject_type' => $subject->getMorphClass(),
 *             'subject_id' => $subject->getKey(),
 *             'data' => ['permissions' => $permissions],
 *         ]);
 *     }
 *     // ... other methods
 * }
 * ```
 */
interface AuditLogger
{
    /**
     * Log that permissions were granted to a subject.
     *
     * @param  Model  $subject  The model receiving permissions
     * @param  array<string>  $permissions  The permission names granted
     * @param  Model|null  $context  Optional context for scoped permissions
     */
    public function logPermissionGranted(Model $subject, array $permissions, ?Model $context = null): void;

    /**
     * Log that permissions were revoked from a subject.
     *
     * @param  Model  $subject  The model losing permissions
     * @param  array<string>  $permissions  The permission names revoked
     * @param  Model|null  $context  Optional context for scoped permissions
     */
    public function logPermissionRevoked(Model $subject, array $permissions, ?Model $context = null): void;

    /**
     * Log that roles were assigned to a subject.
     *
     * @param  Model  $subject  The model receiving roles
     * @param  array<string>  $roles  The role names assigned
     * @param  Model|null  $context  Optional context for scoped roles
     */
    public function logRoleAssigned(Model $subject, array $roles, ?Model $context = null): void;

    /**
     * Log that roles were removed from a subject.
     *
     * @param  Model  $subject  The model losing roles
     * @param  array<string>  $roles  The role names removed
     * @param  Model|null  $context  Optional context for scoped roles
     */
    public function logRoleRemoved(Model $subject, array $roles, ?Model $context = null): void;

    /**
     * Log a permission check (when log_checks is enabled).
     *
     * @param  Model  $subject  The model being checked
     * @param  string  $permission  The permission being checked
     * @param  bool  $result  Whether the check passed
     * @param  Model|null  $context  Optional context for scoped checks
     */
    public function logPermissionCheck(Model $subject, string $permission, bool $result, ?Model $context = null): void;

    /**
     * Log a role check (when log_checks is enabled).
     *
     * @param  Model  $subject  The model being checked
     * @param  string  $role  The role being checked
     * @param  bool  $result  Whether the check passed
     * @param  Model|null  $context  Optional context for scoped checks
     */
    public function logRoleCheck(Model $subject, string $role, bool $result, ?Model $context = null): void;

    /**
     * Log an access denial (when log_denials is enabled).
     *
     * @param  Model  $subject  The model that was denied
     * @param  string  $type  The type of check ('permission', 'role', 'capability')
     * @param  string  $name  The name of the permission/role/capability
     * @param  Model|null  $context  Optional context for scoped checks
     */
    public function logAccessDenied(Model $subject, string $type, string $name, ?Model $context = null): void;
}
