<?php

declare(strict_types=1);

use OffloadProject\Mandate\CodeFirst\CapabilityDefinition;
use OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer;
use OffloadProject\Mandate\CodeFirst\PermissionDefinition;
use OffloadProject\Mandate\CodeFirst\RoleDefinition;

describe('DefinitionDiscoverer', function () {
    describe('discoverPermissions', function () {
        beforeEach(function () {
            $this->discoverer = new DefinitionDiscoverer;
            $this->fixturesPath = __DIR__.'/../../Fixtures/CodeFirst';
        });

        it('discovers permissions from directory', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            // All classes with string constants are discovered
            expect($permissions->count())->toBeGreaterThanOrEqual(4);
            expect($permissions->pluck('name')->all())->toContain(
                'article:view',
                'article:create',
                'article:edit',
                'article:delete'
            );
        });

        it('extracts guard from class attribute', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $viewPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'article:view');

            expect($viewPermission->guard)->toBe('web');
        });

        it('extracts label from constant attribute', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $viewPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'article:view');

            expect($viewPermission->label)->toBe('View Articles');
        });

        it('extracts description from constant attribute', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $viewPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'article:view');

            expect($viewPermission->description)->toBe('Allows viewing articles');
        });

        it('records source class and constant', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $viewPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'article:view');

            expect($viewPermission->sourceClass)->toContain('ArticlePermissions');
            expect($viewPermission->sourceConstant)->toBe('VIEW');
        });

        it('returns empty collection for non-existent path', function () {
            $permissions = $this->discoverer->discoverPermissions('/non/existent/path');

            expect($permissions)->toBeEmpty();
        });

        it('extracts capabilities from class-level attribute', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $viewPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'user:view');
            $editPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'user:edit');

            expect($viewPermission->capabilityNames())->toBe(['user-management']);
            expect($editPermission->capabilityNames())->toBe(['user-management']);
        });

        it('merges class-level and constant-level capabilities', function () {
            $permissions = $this->discoverer->discoverPermissions($this->fixturesPath);

            $deletePermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'user:delete');

            expect($deletePermission->capabilityNames())->toContain('user-management', 'admin-only');
            expect(count($deletePermission->capabilities))->toBe(2);
        });

        it('extracts label and description from inline Capability attribute', function () {
            $permissions = $this->discoverer->discoverPermissions(
                __DIR__.'/../../Fixtures/CodeFirstInlineCapabilities'
            );

            $createPermission = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'post:create');
            /** @var CapabilityDefinition $managePosts */
            $managePosts = collect($createPermission->capabilities)
                ->first(fn (CapabilityDefinition $c) => $c->name === 'manage-posts');

            expect($managePosts)->not->toBeNull();
            expect($managePosts->label)->toBe('Manage Posts');
            expect($managePosts->description)->toBe('Create, edit, and publish posts');
            expect($managePosts->guard)->toBe('web');
        });

        it('dedupes inline capabilities by name with first non-null wins', function () {
            $permissions = $this->discoverer->discoverPermissions(
                __DIR__.'/../../Fixtures/CodeFirstInlineCapabilities'
            );

            // CommentPermissions::MODERATE has #[Capability('manage-posts')] (no metadata)
            // and #[Capability('moderation', label: 'Moderation', ...)] — both should be present.
            // Each name appears once.
            $moderate = $permissions->first(fn (PermissionDefinition $p) => $p->name === 'comment:moderate');

            expect($moderate->capabilityNames())->toEqualCanonicalizing(['manage-posts', 'moderation']);

            $managePosts = collect($moderate->capabilities)
                ->first(fn (CapabilityDefinition $c) => $c->name === 'manage-posts');
            // Inline-only on this constant — no label provided
            expect($managePosts->label)->toBeNull();

            $moderation = collect($moderate->capabilities)
                ->first(fn (CapabilityDefinition $c) => $c->name === 'moderation');
            expect($moderation->label)->toBe('Moderation');
            expect($moderation->description)->toBe('Moderate user-generated content');
        });
    });

    describe('discoverRoles', function () {
        beforeEach(function () {
            $this->discoverer = new DefinitionDiscoverer;
            $this->fixturesPath = __DIR__.'/../../Fixtures/CodeFirst';
        });

        it('discovers roles from directory', function () {
            $roles = $this->discoverer->discoverRoles($this->fixturesPath);

            // All classes with string constants are discovered
            expect($roles->count())->toBeGreaterThanOrEqual(3);
            expect($roles->pluck('name')->all())->toContain('admin', 'editor', 'viewer');
        });

        it('extracts label from constant attribute with fallback to class', function () {
            $roles = $this->discoverer->discoverRoles($this->fixturesPath);

            $adminRole = $roles->first(fn (RoleDefinition $r) => $r->name === 'admin');
            $viewerRole = $roles->first(fn (RoleDefinition $r) => $r->name === 'viewer');

            expect($adminRole->label)->toBe('Administrator');
            expect($viewerRole->label)->toBe('System Roles'); // Falls back to class label
        });
    });
});
