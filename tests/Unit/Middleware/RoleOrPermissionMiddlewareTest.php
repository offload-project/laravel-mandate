<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;
use OffloadProject\Mandate\Middleware\RoleOrPermissionMiddleware;
use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->middleware = new RoleOrPermissionMiddleware;
    $this->request = Request::create('/test', 'GET');
});

describe('RoleOrPermissionMiddleware', function () {
    it('allows access when user has required role', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        $user->assignRole('admin');

        Auth::login($user);

        $response = $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('allows access when user has required permission', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $user->grantPermission('article:edit');

        Auth::login($user);

        $response = $this->middleware->handle($this->request, fn () => new Response('OK'), 'article:edit');

        expect($response->getContent())->toBe('OK');
    });

    it('allows access when user has role but not permission', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $user->assignRole('admin');

        Auth::login($user);

        $response = $this->middleware->handle(
            $this->request,
            fn () => new Response('OK'),
            'admin|article:edit'
        );

        expect($response->getContent())->toBe('OK');
    });

    it('allows access when user has permission but not role', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);
        $user->grantPermission('article:edit');

        Auth::login($user);

        $response = $this->middleware->handle(
            $this->request,
            fn () => new Response('OK'),
            'admin|article:edit'
        );

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user has neither role nor permission', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Permission::create(['name' => 'article:edit', 'guard' => 'web']);

        Auth::login($user);

        $this->middleware->handle(
            $this->request,
            fn () => new Response('OK'),
            'admin|article:edit'
        );
    })->throws(UnauthorizedException::class);

    it('denies access when user is not authenticated', function () {
        $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin');
    })->throws(UnauthorizedException::class);

    it('provides static using helper', function () {
        $middleware = RoleOrPermissionMiddleware::using(['admin', 'article:edit']);

        expect($middleware)->toBe('role_or_permission:admin|article:edit');
    });
});
