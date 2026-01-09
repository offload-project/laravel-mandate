<?php

declare(strict_types=1);

namespace OffloadProject\Mandate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use OffloadProject\Mandate\Contracts\AuditLogger;

/**
 * Default audit logger implementation using Laravel's logging.
 *
 * Logs authorization events to Laravel's log system using the 'mandate'
 * channel (or default channel if not configured).
 */
final class DefaultAuditLogger implements AuditLogger
{
    private const CHANNEL = 'mandate';

    public function logPermissionGranted(Model $subject, array $permissions, ?Model $context = null): void
    {
        $this->log('info', 'Permissions granted', [
            'action' => 'permission_granted',
            'subject' => $this->formatSubject($subject),
            'permissions' => $permissions,
            'context' => $this->formatContext($context),
        ]);
    }

    public function logPermissionRevoked(Model $subject, array $permissions, ?Model $context = null): void
    {
        $this->log('info', 'Permissions revoked', [
            'action' => 'permission_revoked',
            'subject' => $this->formatSubject($subject),
            'permissions' => $permissions,
            'context' => $this->formatContext($context),
        ]);
    }

    public function logRoleAssigned(Model $subject, array $roles, ?Model $context = null): void
    {
        $this->log('info', 'Roles assigned', [
            'action' => 'role_assigned',
            'subject' => $this->formatSubject($subject),
            'roles' => $roles,
            'context' => $this->formatContext($context),
        ]);
    }

    public function logRoleRemoved(Model $subject, array $roles, ?Model $context = null): void
    {
        $this->log('info', 'Roles removed', [
            'action' => 'role_removed',
            'subject' => $this->formatSubject($subject),
            'roles' => $roles,
            'context' => $this->formatContext($context),
        ]);
    }

    public function logPermissionCheck(Model $subject, string $permission, bool $result, ?Model $context = null): void
    {
        $this->log('debug', 'Permission check', [
            'action' => 'permission_check',
            'subject' => $this->formatSubject($subject),
            'permission' => $permission,
            'result' => $result ? 'granted' : 'denied',
            'context' => $this->formatContext($context),
        ]);
    }

    public function logRoleCheck(Model $subject, string $role, bool $result, ?Model $context = null): void
    {
        $this->log('debug', 'Role check', [
            'action' => 'role_check',
            'subject' => $this->formatSubject($subject),
            'role' => $role,
            'result' => $result ? 'has_role' : 'no_role',
            'context' => $this->formatContext($context),
        ]);
    }

    public function logAccessDenied(Model $subject, string $type, string $name, ?Model $context = null): void
    {
        $this->log('warning', 'Access denied', [
            'action' => 'access_denied',
            'subject' => $this->formatSubject($subject),
            'type' => $type,
            'name' => $name,
            'context' => $this->formatContext($context),
        ]);
    }

    /**
     * Format a subject model for logging.
     *
     * @return array{type: string, id: int|string}
     */
    private function formatSubject(Model $subject): array
    {
        return [
            'type' => $subject->getMorphClass(),
            'id' => $subject->getKey(),
        ];
    }

    /**
     * Format a context model for logging.
     *
     * @return array{type: string, id: int|string}|null
     */
    private function formatContext(?Model $context): ?array
    {
        if ($context === null) {
            return null;
        }

        return [
            'type' => $context->getMorphClass(),
            'id' => $context->getKey(),
        ];
    }

    /**
     * Write to the log.
     *
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context): void
    {
        // Try to use mandate channel if configured, otherwise use default
        $logger = config('logging.channels.'.self::CHANNEL)
            ? Log::channel(self::CHANNEL)
            : Log::getLogger();

        $logger->log($level, "[Mandate] {$message}", $context);
    }
}
