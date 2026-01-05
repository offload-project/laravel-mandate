<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OffloadProject\Mandate\Http\Middleware\MandateInertiaAuthShare;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Support\MandateUI;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;

beforeEach(function () {
    config()->set('pennant.default', 'array');
    config()->set('pennant.stores.array', ['driver' => 'array']);

    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    app(MandateManager::class)->clearCache();
    app(MandateManager::class)->syncPermissions();
});

describe('MandateInertiaAuthShare Middleware', function () {
    test('it passes through when Inertia is not available', function () {
        // Skip if Inertia is available (we can't unload it)
        if (class_exists(Inertia\Inertia::class)) {
            $this->markTestSkipped('Inertia is available, cannot test fallback behavior');
        }

        $mandateUI = new MandateUI();
        $middleware = new MandateInertiaAuthShare($mandateUI);

        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    test('it can be instantiated with MandateUI dependency', function () {
        $mandateUI = new MandateUI();
        $middleware = new MandateInertiaAuthShare($mandateUI);

        expect($middleware)->toBeInstanceOf(MandateInertiaAuthShare::class);
    });

    test('it handles request with no authenticated user', function () {
        if (! class_exists(Inertia\Inertia::class)) {
            $this->markTestSkipped('Inertia is not available');
        }

        $mandateUI = new MandateUI();
        $middleware = new MandateInertiaAuthShare($mandateUI);

        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });

    test('it shares auth data when user is authenticated', function () {
        if (! class_exists(Inertia\Inertia::class)) {
            $this->markTestSkipped('Inertia is not available');
        }

        $user = MandateUser::create(['email' => 'test@example.com']);
        $user->grant('users.view');

        app(MandateManager::class)->clearCache();

        $mandateUI = new MandateUI();
        $middleware = new MandateInertiaAuthShare($mandateUI);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user->fresh());

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        expect($response->getContent())->toBe('OK');
    });
});
