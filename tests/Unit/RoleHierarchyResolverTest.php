<?php

declare(strict_types=1);

use OffloadProject\Mandate\Data\RoleData;
use OffloadProject\Mandate\Exceptions\CircularRoleInheritanceException;
use OffloadProject\Mandate\Services\RoleHierarchyResolver;

test('it resolves single parent inheritance', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'viewer',
            label: 'Viewer',
            permissions: ['view posts'],
        ),
        new RoleData(
            name: 'editor',
            label: 'Editor',
            permissions: ['edit posts'],
            inheritsFrom: ['viewer'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $viewer = $resolved->firstWhere('name', 'viewer');
    $editor = $resolved->firstWhere('name', 'editor');

    // Viewer has no inherited permissions
    expect($viewer->inheritedPermissions)->toBe([]);
    expect($viewer->inheritsFrom)->toBe([]);

    // Editor inherits from viewer
    expect($editor->inheritedPermissions)->toBe(['view posts']);
    expect($editor->inheritsFrom)->toBe(['viewer']);
    expect($editor->allPermissions())->toBe(['edit posts', 'view posts']);
});

test('it resolves deep inheritance chain', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'viewer',
            label: 'Viewer',
            permissions: ['view posts'],
        ),
        new RoleData(
            name: 'editor',
            label: 'Editor',
            permissions: ['edit posts'],
            inheritsFrom: ['viewer'],
        ),
        new RoleData(
            name: 'admin',
            label: 'Admin',
            permissions: ['delete posts'],
            inheritsFrom: ['editor'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $admin = $resolved->firstWhere('name', 'admin');

    // Admin should inherit from editor AND viewer (through editor)
    expect($admin->inheritedPermissions)->toContain('edit posts');
    expect($admin->inheritedPermissions)->toContain('view posts');
    expect($admin->allPermissions())->toBe(['delete posts', 'edit posts', 'view posts']);
});

test('it resolves multiple parent inheritance', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'content-manager',
            label: 'Content Manager',
            permissions: ['manage content'],
        ),
        new RoleData(
            name: 'billing-admin',
            label: 'Billing Admin',
            permissions: ['view billing'],
        ),
        new RoleData(
            name: 'super-admin',
            label: 'Super Admin',
            permissions: ['system settings'],
            inheritsFrom: ['content-manager', 'billing-admin'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $superAdmin = $resolved->firstWhere('name', 'super-admin');

    // Super admin inherits from both content-manager and billing-admin
    expect($superAdmin->inheritedPermissions)->toContain('manage content');
    expect($superAdmin->inheritedPermissions)->toContain('view billing');
    expect($superAdmin->inheritsFrom)->toBe(['content-manager', 'billing-admin']);
});

test('it deduplicates inherited permissions', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'viewer',
            label: 'Viewer',
            permissions: ['view posts'],
        ),
        new RoleData(
            name: 'content-manager',
            label: 'Content Manager',
            permissions: ['view posts', 'manage content'], // Also has 'view posts'
            inheritsFrom: ['viewer'],
        ),
        new RoleData(
            name: 'super-admin',
            label: 'Super Admin',
            permissions: [],
            inheritsFrom: ['content-manager'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $superAdmin = $resolved->firstWhere('name', 'super-admin');

    // 'view posts' should only appear once
    $viewPostsCount = count(array_filter(
        $superAdmin->inheritedPermissions,
        fn ($p) => $p === 'view posts'
    ));
    expect($viewPostsCount)->toBe(1);
});

test('it throws exception on circular inheritance', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'role-a',
            label: 'Role A',
            permissions: [],
            inheritsFrom: ['role-b'],
        ),
        new RoleData(
            name: 'role-b',
            label: 'Role B',
            permissions: [],
            inheritsFrom: ['role-a'],
        ),
    ]);

    $resolver->resolve($roles);
})->throws(CircularRoleInheritanceException::class);

test('it throws exception on deep circular inheritance', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'role-a',
            label: 'Role A',
            permissions: [],
            inheritsFrom: ['role-b'],
        ),
        new RoleData(
            name: 'role-b',
            label: 'Role B',
            permissions: [],
            inheritsFrom: ['role-c'],
        ),
        new RoleData(
            name: 'role-c',
            label: 'Role C',
            permissions: [],
            inheritsFrom: ['role-a'],
        ),
    ]);

    $resolver->resolve($roles);
})->throws(CircularRoleInheritanceException::class);

test('it handles missing parent roles gracefully', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'editor',
            label: 'Editor',
            permissions: ['edit posts'],
            inheritsFrom: ['non-existent-role'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $editor = $resolved->firstWhere('name', 'editor');

    // Should have no inherited permissions (parent doesn't exist)
    expect($editor->inheritedPermissions)->toBe([]);
    expect($editor->inheritsFrom)->toBe(['non-existent-role']);
});

test('it returns roles without inheritance unchanged', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(
            name: 'standalone',
            label: 'Standalone',
            permissions: ['do something'],
        ),
    ]);

    $resolved = $resolver->resolve($roles);

    $standalone = $resolved->firstWhere('name', 'standalone');

    expect($standalone->inheritedPermissions)->toBe([]);
    expect($standalone->inheritsFrom)->toBe([]);
    expect($standalone->permissions)->toBe(['do something']);
});

test('it builds inheritance chain correctly', function () {
    $resolver = new RoleHierarchyResolver;

    $roles = collect([
        new RoleData(name: 'viewer', label: 'Viewer', permissions: []),
        new RoleData(name: 'editor', label: 'Editor', permissions: [], inheritsFrom: ['viewer']),
        new RoleData(name: 'admin', label: 'Admin', permissions: [], inheritsFrom: ['editor']),
    ]);

    $roleMap = $roles->keyBy('name');
    $admin = $roles->firstWhere('name', 'admin');

    $chain = $resolver->getInheritanceChain($admin, $roleMap);

    // Chain should be: viewer, editor, admin (ancestors first)
    expect($chain)->toBe(['viewer', 'editor', 'admin']);
});
