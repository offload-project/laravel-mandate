<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Mandate as MandateService;
use OffloadProject\Mandate\MandateRegistrar;

/**
 * Facade for the Mandate service.
 *
 * @method static bool hasPermission(Model $subject, string $permission)
 * @method static bool hasAnyPermission(Model $subject, array $permissions)
 * @method static bool hasAllPermissions(Model $subject, array $permissions)
 * @method static bool hasRole(Model $subject, string $role)
 * @method static bool hasAnyRole(Model $subject, array $roles)
 * @method static bool hasAllRoles(Model $subject, array $roles)
 * @method static Collection<int, PermissionContract> getPermissions(Model $subject)
 * @method static Collection<int, string> getPermissionNames(Model $subject)
 * @method static Collection<int, RoleContract> getRoles(Model $subject)
 * @method static Collection<int, string> getRoleNames(Model $subject)
 * @method static PermissionContract createPermission(string $name, ?string $guard = null)
 * @method static PermissionContract findOrCreatePermission(string $name, ?string $guard = null)
 * @method static RoleContract createRole(string $name, ?string $guard = null)
 * @method static RoleContract findOrCreateRole(string $name, ?string $guard = null)
 * @method static Collection<int, PermissionContract> getAllPermissions(?string $guard = null)
 * @method static Collection<int, RoleContract> getAllRoles(?string $guard = null)
 * @method static bool capabilitiesEnabled()
 * @method static bool hasCapability(Model $subject, string $capability)
 * @method static bool hasAnyCapability(Model $subject, array $capabilities)
 * @method static bool hasAllCapabilities(Model $subject, array $capabilities)
 * @method static Collection<int, CapabilityContract> getCapabilities(Model $subject)
 * @method static Collection<int, string> getCapabilityNames(Model $subject)
 * @method static CapabilityContract createCapability(string $name, ?string $guard = null)
 * @method static CapabilityContract findOrCreateCapability(string $name, ?string $guard = null)
 * @method static Collection<int, CapabilityContract> getAllCapabilities(?string $guard = null)
 * @method static bool clearCache()
 * @method static MandateRegistrar getRegistrar()
 * @method static array{permissions: array<string>, roles: array<string>, capabilities?: array<string>} getAuthorizationData(\Illuminate\Contracts\Auth\Authenticatable|null $subject = null)
 *
 * @see MandateService
 */
final class Mandate extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MandateService::class;
    }
}
