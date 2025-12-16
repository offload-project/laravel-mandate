<?php

declare(strict_types=1);

use OffloadProject\Mandate\Data\FeatureData;
use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Data\RoleData;

test('it creates feature data with basic properties', function () {
    $data = new FeatureData(
        class: 'App\Features\ExportFeature',
        name: 'export',
        label: 'Export Feature',
        description: 'Enables data export',
    );

    expect($data->class)->toBe('App\Features\ExportFeature');
    expect($data->name)->toBe('export');
    expect($data->label)->toBe('Export Feature');
    expect($data->description)->toBe('Enables data export');
    expect($data->active)->toBeNull();
    expect($data->permissions)->toBe([]);
    expect($data->roles)->toBe([]);
});

test('it creates feature data with permissions and roles', function () {
    $permission = new PermissionData(name: 'users.export', label: 'Export Users');
    $role = new RoleData(name: 'exporter', label: 'Exporter');

    $data = new FeatureData(
        class: 'App\Features\ExportFeature',
        name: 'export',
        label: 'Export Feature',
        permissions: [$permission],
        roles: [$role],
    );

    expect($data->permissions)->toHaveCount(1);
    expect($data->permissions[0]->name)->toBe('users.export');
    expect($data->roles)->toHaveCount(1);
    expect($data->roles[0]->name)->toBe('exporter');
});

test('it creates feature data with active status', function () {
    $activeFeature = new FeatureData(
        class: 'App\Features\ActiveFeature',
        name: 'active',
        label: 'Active Feature',
        active: true,
    );

    $inactiveFeature = new FeatureData(
        class: 'App\Features\InactiveFeature',
        name: 'inactive',
        label: 'Inactive Feature',
        active: false,
    );

    expect($activeFeature->active)->toBeTrue();
    expect($inactiveFeature->active)->toBeFalse();
});

test('it creates feature data with metadata', function () {
    $data = new FeatureData(
        class: 'App\Features\ExportFeature',
        name: 'export',
        label: 'Export Feature',
        metadata: ['icon' => 'download', 'tier' => 'premium'],
    );

    expect($data->metadata)->toBe(['icon' => 'download', 'tier' => 'premium']);
});
