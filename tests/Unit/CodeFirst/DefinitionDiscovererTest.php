<?php

declare(strict_types=1);

use OffloadProject\Mandate\CodeFirst\DefinitionDiscoverer;
use OffloadProject\Mandate\CodeFirst\PermissionDefinition;
use OffloadProject\Mandate\CodeFirst\RoleDefinition;

describe('DefinitionDiscoverer', function () {
    beforeEach(function () {
        $this->discoverer = new DefinitionDiscoverer;
        $this->fixturesPath = __DIR__.'/../../Fixtures/CodeFirst';
    });

    describe('discoverPermissions', function () {
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
    });

    describe('discoverRoles', function () {
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
