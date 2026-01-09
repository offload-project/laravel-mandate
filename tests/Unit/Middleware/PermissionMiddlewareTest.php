<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;
use OffloadProject\Mandate\Middleware\PermissionMiddleware;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->middleware = new PermissionMiddleware;
    $this->request = Request::create('/test', 'GET');
});

describe('PermissionMiddleware', function () {
    it('allows access when user has required permission', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        $user->grantPermission('article:view');

        Auth::login($user);

        $response = $this->middleware->handle($this->request, fn () => new Response('OK'), 'article:view');

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user lacks required permission', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        Auth::login($user);

        $this->middleware->handle($this->request, fn () => new Response('OK'), 'article:view');
    })->throws(UnauthorizedException::class);

    it('denies access when user is not authenticated', function () {
        $this->request->setUserResolver(fn () => null);

        $this->middleware->handle($this->request, fn () => new Response('OK'), 'article:view');
    })->throws(UnauthorizedException::class);

    it('allows access when user has any of the pipe-separated permissions', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $user->grantPermission('article:edit');

        Auth::login($user);

        $response = $this->middleware->handle(
            $this->request,
            fn () => new Response('OK'),
            'article:view|article:edit'
        );

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user has none of the pipe-separated permissions', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Permission::create(['name' => 'article:view', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        Auth::login($user);

        $this->middleware->handle(
            $this->request,
            fn () => new Response('OK'),
            'article:view|article:edit'
        );
    })->throws(UnauthorizedException::class);

    it('provides static using helper for route definitions', function () {
        $middleware = PermissionMiddleware::using('article:view');

        expect($middleware)->toBe('permission:article:view');
    });

    it('provides static using helper for multiple permissions', function () {
        $middleware = PermissionMiddleware::using(['article:view', 'article:edit']);

        expect($middleware)->toBe('permission:article:view|article:edit');
    });
});
