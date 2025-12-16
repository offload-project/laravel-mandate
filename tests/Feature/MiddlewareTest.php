<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OffloadProject\Mandate\Http\Middleware\MandateFeature;
use OffloadProject\Mandate\Http\Middleware\MandatePermission;
use OffloadProject\Mandate\Http\Middleware\MandateRole;
use OffloadProject\Mandate\Tests\Fixtures\Features\ExportFeature;
use OffloadProject\Mandate\Tests\Fixtures\Permissions\UserPermissions;
use OffloadProject\Mandate\Tests\Fixtures\Roles\SystemRoles;
use Symfony\Component\HttpKernel\Exception\HttpException;

describe('MandatePermission Middleware', function () {
    it('denies access when no user is authenticated', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new MandatePermission;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), UserPermissions::VIEW))
            ->toThrow(HttpException::class);
    });

    it('generates correct middleware string with using() helper for single permission', function () {
        expect(MandatePermission::using(UserPermissions::VIEW))
            ->toBe('mandate.permission:users.view');
    });

    it('generates correct middleware string with using() helper for multiple permissions', function () {
        expect(MandatePermission::using(UserPermissions::VIEW, UserPermissions::UPDATE))
            ->toBe('mandate.permission:users.view,users.update');
    });

    it('generates correct middleware string with using() helper for all permissions', function () {
        expect(MandatePermission::using(
            UserPermissions::VIEW,
            UserPermissions::CREATE,
            UserPermissions::UPDATE,
            UserPermissions::DELETE
        ))->toBe('mandate.permission:users.view,users.create,users.update,users.delete');
    });
});

describe('MandateRole Middleware', function () {
    it('denies access when no user is authenticated', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new MandateRole;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), SystemRoles::ADMIN))
            ->toThrow(HttpException::class);
    });

    it('generates correct middleware string with using() helper for single role', function () {
        expect(MandateRole::using(SystemRoles::ADMIN))
            ->toBe('mandate.role:admin');
    });

    it('generates correct middleware string with using() helper for multiple roles', function () {
        expect(MandateRole::using(SystemRoles::ADMIN, SystemRoles::EDITOR))
            ->toBe('mandate.role:admin,editor');
    });

    it('generates correct middleware string with using() helper for all roles', function () {
        expect(MandateRole::using(SystemRoles::ADMIN, SystemRoles::EDITOR, SystemRoles::VIEWER))
            ->toBe('mandate.role:admin,editor,viewer');
    });
});

describe('MandateFeature Middleware', function () {
    it('denies access when no user is authenticated', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new MandateFeature;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), ExportFeature::class))
            ->toThrow(HttpException::class);
    });

    it('generates correct middleware string with using() helper', function () {
        expect(MandateFeature::using(ExportFeature::class))
            ->toBe('mandate.feature:'.ExportFeature::class);
    });
});

describe('Middleware Registration', function () {
    it('registers mandate.permission middleware alias', function () {
        $router = app('router');

        expect($router->getMiddleware())
            ->toHaveKey('mandate.permission');
    });

    it('registers mandate.role middleware alias', function () {
        $router = app('router');

        expect($router->getMiddleware())
            ->toHaveKey('mandate.role');
    });

    it('registers mandate.feature middleware alias', function () {
        $router = app('router');

        expect($router->getMiddleware())
            ->toHaveKey('mandate.feature');
    });
});
