<?php

declare(strict_types=1);

use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Tests\Fixtures\Roles\SystemRoles;

test('it creates role data from class constant', function () {
    $data = RoleData::fromClassConstant(SystemRoles::class, 'ADMIN');

    expect($data->name)->toBe('admin');
    expect($data->label)->toBe('Administrator');
    expect($data->set)->toBe('system');
});

test('it determines availability based on feature', function () {
    // No feature = always available
    $noFeature = new RoleData(
        name: 'test',
        label: 'Test',
        feature: null,
    );
    expect($noFeature->isAvailable())->toBeTrue();

    // Has feature, feature is active
    $activeFeature = new RoleData(
        name: 'test',
        label: 'Test',
        feature: 'SomeFeature',
        featureActive: true,
    );
    expect($activeFeature->isAvailable())->toBeTrue();

    // Has feature, feature is inactive
    $inactiveFeature = new RoleData(
        name: 'test',
        label: 'Test',
        feature: 'SomeFeature',
        featureActive: false,
    );
    expect($inactiveFeature->isAvailable())->toBeFalse();
});

test('it determines if role is assigned', function () {
    // Has role, no feature
    $assigned = new RoleData(
        name: 'test',
        label: 'Test',
        active: true,
    );
    expect($assigned->isAssigned())->toBeTrue();

    // Has role, feature active
    $assignedWithFeature = new RoleData(
        name: 'test',
        label: 'Test',
        active: true,
        feature: 'SomeFeature',
        featureActive: true,
    );
    expect($assignedWithFeature->isAssigned())->toBeTrue();

    // Has role, feature inactive
    $notAssigned = new RoleData(
        name: 'test',
        label: 'Test',
        active: true,
        feature: 'SomeFeature',
        featureActive: false,
    );
    expect($notAssigned->isAssigned())->toBeFalse();

    // No role
    $noRole = new RoleData(
        name: 'test',
        label: 'Test',
        active: false,
    );
    expect($noRole->isAssigned())->toBeFalse();
});

test('it creates copy with updated status via withStatus()', function () {
    $original = new RoleData(
        name: 'test',
        label: 'Test',
        description: 'Test description',
        set: 'testing',
        guard: 'web',
        feature: 'SomeFeature',
        permissions: ['view users', 'create users'],
        metadata: ['icon' => 'shield'],
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
    expect($updated->permissions)->toBe(['view users', 'create users']);
    expect($updated->metadata)->toBe(['icon' => 'shield']);
});

test('it creates copy with additional metadata via withMetadata()', function () {
    $original = new RoleData(
        name: 'test',
        label: 'Test',
        metadata: ['icon' => 'shield'],
    );

    $updated = $original->withMetadata(['color' => 'red', 'level' => 5]);

    // Original unchanged
    expect($original->metadata)->toBe(['icon' => 'shield']);

    // Updated has merged metadata
    expect($updated->metadata)->toBe([
        'icon' => 'shield',
        'color' => 'red',
        'level' => 5,
    ]);
});

test('withMetadata() overwrites existing keys', function () {
    $original = new RoleData(
        name: 'test',
        label: 'Test',
        metadata: ['icon' => 'shield', 'level' => 1],
    );

    $updated = $original->withMetadata(['level' => 10]);

    expect($updated->metadata)->toBe([
        'icon' => 'shield',
        'level' => 10,
    ]);
});

test('it creates role data with permissions array', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['view users', 'create users', 'delete users'],
    );

    expect($data->permissions)->toBe(['view users', 'create users', 'delete users']);
});

test('it returns all permissions (direct + inherited)', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users', 'create users'],
    );

    expect($data->allPermissions())->toBe(['delete users', 'view users', 'create users']);
});

test('it deduplicates all permissions', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['view users', 'delete users'],
        inheritedPermissions: ['view users', 'create users'],
    );

    $allPermissions = $data->allPermissions();

    // 'view users' should only appear once
    expect(count(array_filter($allPermissions, fn ($p) => $p === 'view users')))->toBe(1);
    expect($allPermissions)->toContain('view users');
    expect($allPermissions)->toContain('delete users');
    expect($allPermissions)->toContain('create users');
});

test('it checks if has permission (direct)', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
    );

    expect($data->granted('delete users'))->toBeTrue();
});

test('it checks if has permission (inherited)', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
    );

    expect($data->granted('view users'))->toBeTrue();
});

test('it returns false for non-existent permission', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
    );

    expect($data->granted('fly'))->toBeFalse();
});

test('it identifies inherited permissions', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
    );

    expect($data->isInheritedPermission('view users'))->toBeTrue();
    expect($data->isInheritedPermission('delete users'))->toBeFalse();
});

test('direct permission is not considered inherited', function () {
    $data = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['view users'],
        inheritedPermissions: ['view users'], // Same permission in both
    );

    // Direct permission takes precedence, so it's not "inherited"
    expect($data->isInheritedPermission('view users'))->toBeFalse();
});

test('it creates copy with inheritance via withInheritance()', function () {
    $original = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
    );

    $withInheritance = $original->withInheritance(
        inheritedPermissions: ['view users', 'create users'],
        inheritsFrom: ['editor'],
    );

    // Original unchanged
    expect($original->inheritedPermissions)->toBe([]);
    expect($original->inheritsFrom)->toBe([]);

    // Copy has inheritance
    expect($withInheritance->inheritedPermissions)->toBe(['view users', 'create users']);
    expect($withInheritance->inheritsFrom)->toBe(['editor']);
    expect($withInheritance->permissions)->toBe(['delete users']);
});

test('withStatus preserves inheritance properties', function () {
    $original = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
        inheritsFrom: ['editor'],
    );

    $updated = $original->withStatus(active: true, featureActive: true);

    expect($updated->inheritedPermissions)->toBe(['view users']);
    expect($updated->inheritsFrom)->toBe(['editor']);
});

test('withMetadata preserves inheritance properties', function () {
    $original = new RoleData(
        name: 'admin',
        label: 'Administrator',
        permissions: ['delete users'],
        inheritedPermissions: ['view users'],
        inheritsFrom: ['editor'],
    );

    $updated = $original->withMetadata(['icon' => 'shield']);

    expect($updated->inheritedPermissions)->toBe(['view users']);
    expect($updated->inheritsFrom)->toBe(['editor']);
});
