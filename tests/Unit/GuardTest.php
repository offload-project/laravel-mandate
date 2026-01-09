<?php

declare(strict_types=1);

use OffloadProject\Mandate\Exceptions\GuardMismatchException;
use OffloadProject\Mandate\Guard;
use OffloadProject\Mandate\Tests\Fixtures\User;

describe('Guard Helper', function () {
    describe('getDefaultName', function () {
        it('returns default guard from config', function () {
            $default = Guard::getDefaultName();

            expect($default)->toBe('web');
        });
    });

    describe('getNameForModel', function () {
        it('returns guard name from model property', function () {
            $user = new class extends User
            {
                public ?string $guard_name = 'api';
            };

            $guard = Guard::getNameForModel($user);

            expect($guard)->toBe('api');
        });

        it('returns default guard when model has no guard_name', function () {
            $user = new User;

            $guard = Guard::getNameForModel($user);

            expect($guard)->toBe('web');
        });

        it('resolves guard from auth config for User model', function () {
            $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);

            $guard = Guard::getNameForModel($user);

            expect($guard)->toBe('web');
        });
    });

    describe('getAllNames', function () {
        it('returns all configured guards', function () {
            $guards = Guard::getAllNames();

            expect($guards)->toContain('web');
        });
    });

    describe('exists', function () {
        it('returns true for existing guard', function () {
            expect(Guard::exists('web'))->toBeTrue();
        });

        it('returns false for non-existing guard', function () {
            expect(Guard::exists('nonexistent'))->toBeFalse();
        });
    });

    describe('assertMatch', function () {
        it('does not throw when guards match', function () {
            Guard::assertMatch('web', 'web');

            expect(true)->toBeTrue();
        });

        it('throws GuardMismatchException for permission context', function () {
            Guard::assertMatch('web', 'api', 'permission');
        })->throws(GuardMismatchException::class);

        it('throws GuardMismatchException for role context', function () {
            Guard::assertMatch('web', 'api', 'role');
        })->throws(GuardMismatchException::class);
    });

    describe('getModelClassForGuard', function () {
        it('returns model class for guard', function () {
            $model = Guard::getModelClassForGuard('web');

            expect($model)->toBe(User::class);
        });

        it('returns null for invalid guard', function () {
            $model = Guard::getModelClassForGuard('nonexistent');

            expect($model)->toBeNull();
        });
    });
});
