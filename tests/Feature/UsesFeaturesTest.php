<?php

declare(strict_types=1);

use Laravel\Pennant\Feature;
use OffloadProject\Mandate\Services\MandateManager;
use OffloadProject\Mandate\Tests\Fixtures\MandateUser;

beforeEach(function () {
    // Configure Pennant to use array driver (in-memory)
    config()->set('pennant.default', 'array');
    config()->set('pennant.stores.array', ['driver' => 'array']);

    // Create the users table
    $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamps();
    });

    // Clear caches
    app(MandateManager::class)->clearCache();
});

describe('UsesFeatures Trait', function () {
    test('hasAccess checks Pennant for feature access', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        // Feature is not activated yet
        expect($user->hasAccess('test-feature'))->toBeFalse();

        // Activate the feature
        Feature::for($user)->activate('test-feature');

        expect($user->hasAccess('test-feature'))->toBeTrue();
    });

    test('enabled is alias for hasAccess', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('my-feature');

        expect($user->enabled('my-feature'))->toBeTrue();
        expect($user->enabled('other-feature'))->toBeFalse();
    });

    test('disabled returns inverse of hasAccess', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('active-feature');

        expect($user->disabled('active-feature'))->toBeFalse();
        expect($user->disabled('inactive-feature'))->toBeTrue();
    });

    test('hasAnyAccess returns true if any feature is active', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('feature-a');

        expect($user->hasAnyAccess(['feature-a', 'feature-b']))->toBeTrue();
        expect($user->hasAnyAccess(['feature-c', 'feature-d']))->toBeFalse();
    });

    test('anyEnabled is alias for hasAnyAccess', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('feature-x');

        expect($user->anyEnabled(['feature-x', 'feature-y']))->toBeTrue();
    });

    test('hasAllAccess returns true only if all features are active', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('feature-1');
        Feature::for($user)->activate('feature-2');

        expect($user->hasAllAccess(['feature-1', 'feature-2']))->toBeTrue();
        expect($user->hasAllAccess(['feature-1', 'feature-3']))->toBeFalse();
    });

    test('allEnabled is alias for hasAllAccess', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('a');
        Feature::for($user)->activate('b');

        expect($user->allEnabled(['a', 'b']))->toBeTrue();
    });

    test('allDisabled returns true if all features are inactive', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        expect($user->allDisabled(['inactive-1', 'inactive-2']))->toBeTrue();

        Feature::for($user)->activate('inactive-1');

        expect($user->allDisabled(['inactive-1', 'inactive-2']))->toBeFalse();
    });

    test('anyDisabled returns true if any feature is inactive', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        Feature::for($user)->activate('active');

        expect($user->anyDisabled(['active', 'inactive']))->toBeTrue();
        expect($user->anyDisabled(['active']))->toBeFalse();
    });

    test('enable returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $result = $user->enable('enable-test');

        expect($result)->toBe($user);
    });

    test('disable returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $result = $user->disable('disable-test');

        expect($result)->toBe($user);
    });

    test('forget returns self for chaining', function () {
        $user = MandateUser::create(['email' => 'test@example.com']);

        $result = $user->forget('forget-test');

        expect($result)->toBe($user);
    });
});
