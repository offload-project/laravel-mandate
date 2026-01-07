<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;
use OffloadProject\Mandate\Middleware\RoleMiddleware;
use OffloadProject\Mandate\Models\Role;
use OffloadProject\Mandate\Tests\Fixtures\User;

beforeEach(function () {
    $this->middleware = new RoleMiddleware;
    $this->request = Request::create('/test', 'GET');
});

describe('RoleMiddleware', function () {
    it('allows access when user has required role', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        $user->assignRole('admin');

        Auth::login($user);

        $response = $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user lacks required role', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);

        Auth::login($user);

        $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin');
    })->throws(UnauthorizedException::class);

    it('denies access when user is not authenticated', function () {
        $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin');
    })->throws(UnauthorizedException::class);

    it('allows access when user has any of the pipe-separated roles', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);
        $user->assignRole('editor');

        Auth::login($user);

        $response = $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin|editor');

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user has none of the pipe-separated roles', function () {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        Role::create(['name' => 'admin', 'guard' => 'web']);
        Role::create(['name' => 'editor', 'guard' => 'web']);

        Auth::login($user);

        $this->middleware->handle($this->request, fn () => new Response('OK'), 'admin|editor');
    })->throws(UnauthorizedException::class);

    it('provides static using helper for route definitions', function () {
        $middleware = RoleMiddleware::using('admin');

        expect($middleware)->toBe('role:admin');
    });

    it('provides static using helper for multiple roles', function () {
        $middleware = RoleMiddleware::using(['admin', 'editor']);

        expect($middleware)->toBe('role:admin|editor');
    });
});
