<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OffloadProject\Mandate\AuthorizationBuilder;
use OffloadProject\Mandate\Contracts\Capability as CapabilityContract;
use OffloadProject\Mandate\Contracts\FeatureAccessHandler;
use OffloadProject\Mandate\Contracts\Permission as PermissionContract;
use OffloadProject\Mandate\Contracts\Role as RoleContract;
use OffloadProject\Mandate\Mandate as MandateService;
use OffloadProject\Mandate\MandateRegistrar;
use OffloadProject\Mandate\SyncResult;

/**
 * Facade for the Mandate service.
 *
 * Fluent authorization builder:
 *
 * @method static AuthorizationBuilder for(Model $subject) Create a fluent authorization builder
 *
 * Permission checks (with optional context for multi-tenancy):
 * @method static bool hasPermission(Model $subject, string $permission, ?Model $context = null)
 * @method static bool hasAnyPermission(Model $subject, array $permissions, ?Model $context = null)
 * @method static bool hasAllPermissions(Model $subject, array $permissions, ?Model $context = null)
 *
 * Role checks (with optional context for multi-tenancy):
 * @method static bool hasRole(Model $subject, string $role, ?Model $context = null)
 * @method static bool hasAnyRole(Model $subject, array $roles, ?Model $context = null)
 * @method static bool hasAllRoles(Model $subject, array $roles, ?Model $context = null)
 *
 * Getting permissions and roles (with optional context filtering):
 * @method static Collection<int, PermissionContract> getPermissions(Model $subject, ?Model $context = null)
 * @method static Collection<int, string> getPermissionNames(Model $subject, ?Model $context = null)
 * @method static Collection<int, RoleContract> getRoles(Model $subject, ?Model $context = null)
 * @method static Collection<int, string> getRoleNames(Model $subject, ?Model $context = null)
 *
 * Context queries:
 * @method static Collection<int, Model> getRoleContexts(Model $subject, string $role)
 * @method static Collection<int, Model> getPermissionContexts(Model $subject, string $permission)
 * @method static bool contextEnabled()
 *
 * Feature integration (requires features.enabled = true and context.enabled = true):
 * @method static bool featureIntegrationEnabled()
 * @method static bool isFeatureContext(?Model $context)
 * @method static FeatureAccessHandler|null getFeatureAccessHandler()
 * @method static bool isFeatureActive(Model $feature)
 * @method static bool hasFeatureAccess(Model $feature, Model $subject)
 * @method static bool canAccessFeature(Model $feature, Model $subject)
 *
 * Creating permissions and roles:
 * @method static PermissionContract createPermission(string $name, ?string $guard = null)
 * @method static PermissionContract findOrCreatePermission(string $name, ?string $guard = null)
 * @method static RoleContract createRole(string $name, ?string $guard = null)
 * @method static RoleContract findOrCreateRole(string $name, ?string $guard = null)
 * @method static Collection<int, PermissionContract> getAllPermissions(?string $guard = null)
 * @method static Collection<int, RoleContract> getAllRoles(?string $guard = null)
 *
 * Capabilities (requires capabilities.enabled = true):
 * @method static bool capabilitiesEnabled()
 * @method static bool hasCapability(Model $subject, string $capability)
 * @method static bool hasAnyCapability(Model $subject, array $capabilities)
 * @method static bool hasAllCapabilities(Model $subject, array $capabilities)
 * @method static Collection<int, CapabilityContract> getCapabilities(Model $subject)
 * @method static Collection<int, string> getCapabilityNames(Model $subject)
 * @method static CapabilityContract createCapability(string $name, ?string $guard = null)
 * @method static CapabilityContract findOrCreateCapability(string $name, ?string $guard = null)
 * @method static Collection<int, CapabilityContract> getAllCapabilities(?string $guard = null)
 *
 * Sync (programmatic equivalent of mandate:sync command):
 * @method static SyncResult sync(bool $permissions = false, bool $roles = false, bool $capabilities = false, bool $seed = false, ?string $guard = null)
 *
 * Utility:
 * @method static bool clearCache()
 * @method static MandateRegistrar getRegistrar()
 *                                                # pint ignore/next-line
 * @method static array{permissions: array<string>, roles: array<string>, capabilities?: array<string>} getAuthorizationData(\Illuminate\Contracts\Auth\Authenticatable|null $subject = null, ?Model $context = null)
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
