<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Contracts;

use Illuminate\Support\Collection;
use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;

/**
 * Contract for database synchronization operations.
 */
interface DatabaseSyncerContract
{
    /**
     * Sync permissions to the database.
     *
     * @param  Collection<int, PermissionData>  $permissions
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncPermissions(
        Collection $permissions,
        array $syncColumns,
        ?string $guard = null,
    ): array;

    /**
     * Sync roles to the database.
     *
     * @param  Collection<int, RoleData>  $roles
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int, permissions_synced: int}
     */
    public function syncRoles(
        Collection $roles,
        array $syncColumns,
        ?string $guard = null,
        bool $seed = false,
    ): array;

    /**
     * Sync features to the database.
     *
     * @param  Collection<int, FeatureData>  $features
     * @param  array<string>  $syncColumns
     * @return array{created: int, existing: int, updated: int}
     */
    public function syncFeatures(
        Collection $features,
        array $syncColumns,
    ): array;

    /**
     * Sync feature-role associations from config.
     *
     * @param  array<string, array<string>>  $featureRolesConfig
     * @return array{assigned: int}
     */
    public function syncFeatureRoles(
        array $featureRolesConfig,
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array;

    /**
     * Sync feature-permission associations from config.
     *
     * @param  array<string, array<string>>  $featurePermissionsConfig
     * @return array{granted: int}
     */
    public function syncFeaturePermissions(
        array $featurePermissionsConfig,
        ?string $guard = null,
        bool $seed = false,
        ?string $scope = 'feature',
        ?string $contextModelType = null,
    ): array;
}
