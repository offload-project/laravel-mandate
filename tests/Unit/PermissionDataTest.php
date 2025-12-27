<?php

declare(strict_types=1);

use OffloadProject\Mandate\Data\PermissionData;
use OffloadProject\Mandate\Tests\Fixtures\Permissions\UserPermissions;

test('it creates permission data from class constant', function () {
    $data = PermissionData::fromClassConstant(UserPermissions::class, 'VIEW');

    expect($data->name)->toBe('view users');
    expect($data->label)->toBe('View Users');
    expect($data->set)->toBe('users');
});

test('it creates permission data with description', function () {
    $data = PermissionData::fromClassConstant(UserPermissions::class, 'EXPORT');

    expect($data->name)->toBe('export users');
    expect($data->label)->toBe('Export Users');
    expect($data->description)->toBe('Export user data to CSV');
});

test('it creates simple permission from name', function () {
    $data = PermissionData::simple('create posts', 'Create Posts', 'posts');

    expect($data->name)->toBe('create posts');
    expect($data->label)->toBe('Create Posts');
    expect($data->set)->toBe('posts');
});

test('it determines availability based on feature', function () {
    // No feature = always available
    $noFeature = new PermissionData(
        name: 'test',
        label: 'Test',
        feature: null,
    );
    expect($noFeature->isAvailable())->toBeTrue();

    // Has feature, feature is active
    $activeFeature = new PermissionData(
        name: 'test',
        label: 'Test',
        feature: 'SomeFeature',
        featureActive: true,
    );
    expect($activeFeature->isAvailable())->toBeTrue();

    // Has feature, feature is inactive
    $inactiveFeature = new PermissionData(
        name: 'test',
        label: 'Test',
        feature: 'SomeFeature',
        featureActive: false,
    );
    expect($inactiveFeature->isAvailable())->toBeFalse();
});

test('it determines if permission is granted', function () {
    // Has permission, no feature
    $granted = new PermissionData(
        name: 'test',
        label: 'Test',
        active: true,
    );
    expect($granted->isGranted())->toBeTrue();

    // Has permission, feature active
    $grantedWithFeature = new PermissionData(
        name: 'test',
        label: 'Test',
        active: true,
        feature: 'SomeFeature',
        featureActive: true,
    );
    expect($grantedWithFeature->isGranted())->toBeTrue();

    // Has permission, feature inactive
    $notGranted = new PermissionData(
        name: 'test',
        label: 'Test',
        active: true,
        feature: 'SomeFeature',
        featureActive: false,
    );
    expect($notGranted->isGranted())->toBeFalse();

    // No permission
    $noPermission = new PermissionData(
        name: 'test',
        label: 'Test',
        active: false,
    );
    expect($noPermission->isGranted())->toBeFalse();
});

test('it creates copy with updated status via withStatus()', function () {
    $original = new PermissionData(
        name: 'test',
        label: 'Test',
        description: 'Test description',
        set: 'testing',
        guard: 'web',
        feature: 'SomeFeature',
        metadata: ['icon' => 'check'],
    );

    $updated = $original->withStatus(active: true, featureActive: false);

    // Original unchanged
    expect($original->active)->toBeNull();
    expect($original->featureActive)->toBeNull();

    // Updated has new status
    expect($updated->active)->toBeTrue();
    expect($updated->featureActive)->toBeFalse();

    // Other properties preserved
    expect($updated->name)->toBe('test');
    expect($updated->label)->toBe('Test');
    expect($updated->description)->toBe('Test description');
    expect($updated->set)->toBe('testing');
    expect($updated->guard)->toBe('web');
    expect($updated->feature)->toBe('SomeFeature');
    expect($updated->metadata)->toBe(['icon' => 'check']);
});

test('it creates copy with additional metadata via withMetadata()', function () {
    $original = new PermissionData(
        name: 'test',
        label: 'Test',
        metadata: ['icon' => 'check'],
    );

    $updated = $original->withMetadata(['color' => 'blue', 'priority' => 1]);

    // Original unchanged
    expect($original->metadata)->toBe(['icon' => 'check']);

    // Updated has merged metadata
    expect($updated->metadata)->toBe([
        'icon' => 'check',
        'color' => 'blue',
        'priority' => 1,
    ]);
});

test('withMetadata() overwrites existing keys', function () {
    $original = new PermissionData(
        name: 'test',
        label: 'Test',
        metadata: ['icon' => 'check', 'color' => 'red'],
    );

    $updated = $original->withMetadata(['color' => 'blue']);

    expect($updated->metadata)->toBe([
        'icon' => 'check',
        'color' => 'blue',
    ]);
});

test('it generates label from dot notation name', function () {
    $data = PermissionData::simple('view users');
    expect($data->label)->toBe('View Users');

    $data2 = PermissionData::simple('create posts');
    expect($data2->label)->toBe('Create Posts');
});

test('it generates label from snake_case name', function () {
    $data = PermissionData::simple('view_users');
    expect($data->label)->toBe('View Users');

    $data2 = PermissionData::simple('CREATE_POST');
    expect($data2->label)->toBe('Create Post');
});
