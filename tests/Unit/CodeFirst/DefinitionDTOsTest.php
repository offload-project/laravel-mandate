<?php

declare(strict_types=1);

use OffloadProject\Mandate\CodeFirst\CapabilityDefinition;
use OffloadProject\Mandate\CodeFirst\PermissionDefinition;
use OffloadProject\Mandate\CodeFirst\RoleDefinition;

describe('Definition DTOs', function () {
    describe('PermissionDefinition', function () {
        it('creates from attributes array', function () {
            $definition = PermissionDefinition::fromAttributes([
                'name' => 'article:view',
                'guard' => 'web',
                'label' => 'View Articles',
                'description' => 'Allows viewing articles',
                'context' => 'App\\Models\\Article',
                'capabilities' => ['content-management'],
                'source_class' => 'App\\Permissions\\ArticlePermissions',
                'source_constant' => 'VIEW',
            ]);

            expect($definition->name)->toBe('article:view');
            expect($definition->guard)->toBe('web');
            expect($definition->label)->toBe('View Articles');
            expect($definition->description)->toBe('Allows viewing articles');
            expect($definition->contextClass)->toBe('App\\Models\\Article');
            expect($definition->capabilities)->toBe(['content-management']);
            expect($definition->sourceClass)->toBe('App\\Permissions\\ArticlePermissions');
            expect($definition->sourceConstant)->toBe('VIEW');
        });

        it('uses default values for missing attributes', function () {
            $definition = PermissionDefinition::fromAttributes([
                'name' => 'test.permission',
            ]);

            expect($definition->guard)->toBe('web');
            expect($definition->label)->toBeNull();
            expect($definition->description)->toBeNull();
            expect($definition->contextClass)->toBeNull();
            expect($definition->capabilities)->toBe([]);
            expect($definition->sourceClass)->toBe('');
            expect($definition->sourceConstant)->toBe('');
        });

        it('generates unique identifier', function () {
            $definition = new PermissionDefinition(
                name: 'article:view',
                guard: 'api'
            );

            expect($definition->getIdentifier())->toBe('api:article:view');
        });
    });

    describe('RoleDefinition', function () {
        it('creates from attributes array', function () {
            $definition = RoleDefinition::fromAttributes([
                'name' => 'admin',
                'guard' => 'api',
                'label' => 'Administrator',
                'description' => 'Full access',
                'source_class' => 'App\\Roles\\SystemRoles',
                'source_constant' => 'ADMIN',
            ]);

            expect($definition->name)->toBe('admin');
            expect($definition->guard)->toBe('api');
            expect($definition->label)->toBe('Administrator');
            expect($definition->description)->toBe('Full access');
            expect($definition->sourceClass)->toBe('App\\Roles\\SystemRoles');
            expect($definition->sourceConstant)->toBe('ADMIN');
        });

        it('generates unique identifier', function () {
            $definition = new RoleDefinition(
                name: 'admin',
                guard: 'web'
            );

            expect($definition->getIdentifier())->toBe('web:admin');
        });
    });

    describe('CapabilityDefinition', function () {
        it('creates from attributes array', function () {
            $definition = CapabilityDefinition::fromAttributes([
                'name' => 'content-management',
                'guard' => 'web',
                'label' => 'Content Management',
                'description' => 'Manage content',
                'source_class' => 'App\\Capabilities\\ContentCapabilities',
                'source_constant' => 'MANAGE_CONTENT',
            ]);

            expect($definition->name)->toBe('content-management');
            expect($definition->guard)->toBe('web');
            expect($definition->label)->toBe('Content Management');
            expect($definition->description)->toBe('Manage content');
            expect($definition->sourceClass)->toBe('App\\Capabilities\\ContentCapabilities');
            expect($definition->sourceConstant)->toBe('MANAGE_CONTENT');
        });

        it('generates unique identifier', function () {
            $definition = new CapabilityDefinition(
                name: 'content-management',
                guard: 'web'
            );

            expect($definition->getIdentifier())->toBe('web:content-management');
        });
    });
});
