<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Services\FeatureRegistry;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Services\PermissionRegistry;
use OffloadProject\Mandate\Services\RoleRegistry;

/**
 * @method static FeatureRegistry features()
 * @method static PermissionRegistry permissions()
 * @method static RoleRegistry roles()
 * @method static FeatureData|null feature(string $class)
 * @method static PermissionData|null permission(string $permission)
 * @method static RoleData|null role(string $role)
 * @method static bool can(Model $model, string $permission)
 * @method static bool hasRole(Model $model, string $role)
 * @method static Collection<int, PermissionData> grantedPermissions(Model $model)
 * @method static Collection<int, RoleData> assignedRoles(Model $model)
 * @method static Collection<int, PermissionData> availablePermissions(Model $model)
 * @method static Collection<int, RoleData> availableRoles(Model $model)
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
