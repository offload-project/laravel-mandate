<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OffloadProject\Mandate\Contracts\FeatureRegistryContract;
use OffloadProject\Mandate\Contracts\PermissionRegistryContract;
use OffloadProject\Mandate\Contracts\RoleRegistryContract;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Support\ModelScope;

/**
 * @method static FeatureRegistryContract features()
 * @method static PermissionRegistryContract permissions()
 * @method static RoleRegistryContract roles()
 * @method static FeatureData|null feature(string $class)
 * @method static PermissionData|null permission(string $permission)
 * @method static RoleData|null role(string $role)
 * @method static ModelScope for(Model $model)
 * @method static bool can(Model $model, string $permission)
 * @method static bool assignedRole(Model $model, string $role)
 * @method static Collection<int, PermissionData> grantedPermissions(Model $model)
 * @method static Collection<int, RoleData> assignedRoles(Model $model)
 * @method static Collection<int, PermissionData> availablePermissions(Model $model)
 * @method static Collection<int, RoleData> availableRoles(Model $model)
 * @method static void enableFeature(Model|string $scope, string $feature)
 * @method static void disableFeature(Model|string $scope, string $feature)
 * @method static void enableForAll(string $feature)
 * @method static void disableForAll(string $feature)
 * @method static void purgeFeature(string|array $features)
 * @method static void forgetFeature(Model|string $scope, string $feature)
 * @method static array syncPermissions(?string $guard = null)
 * @method static array syncRoles(?string $guard = null, bool $seed = false)
 * @method static array syncFeatures()
 * @method static array sync(?string $guard = null, bool $seed = false)
 * @method static array syncAll(?string $guard = null, bool $seed = false)
 * @method static void clearCache()
 *
 * @see MandateManager
 */
final class Mandate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MandateManager::class;
    }
}
