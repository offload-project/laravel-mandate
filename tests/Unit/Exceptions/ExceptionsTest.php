<?php

declare(strict_types=1);

use OffloadProject\Mandate\Exceptions\GuardMismatchException;
use OffloadProject\Mandate\Exceptions\PermissionAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\PermissionNotFoundException;
use OffloadProject\Mandate\Exceptions\RoleAlreadyExistsException;
use OffloadProject\Mandate\Exceptions\RoleNotFoundException;
use OffloadProject\Mandate\Exceptions\UnauthorizedException;

describe('GuardMismatchException', function () {
    it('creates exception for permission guard mismatch', function () {
        $exception = GuardMismatchException::forPermission('web', 'api');

        expect($exception->getMessage())->toContain('web')
            ->and($exception->getMessage())->toContain('api');
    });

    it('creates exception for role guard mismatch', function () {
        $exception = GuardMismatchException::forRole('web', 'api');

        expect($exception->getMessage())->toContain('web')
            ->and($exception->getMessage())->toContain('api');
    });
});

describe('PermissionAlreadyExistsException', function () {
    it('includes permission name and guard in message', function () {
        $exception = PermissionAlreadyExistsException::create('article:view', 'web');

        expect($exception->getMessage())->toContain('article:view')
            ->and($exception->getMessage())->toContain('web');
    });
});

describe('PermissionNotFoundException', function () {
    it('creates exception for name lookup', function () {
        $exception = PermissionNotFoundException::withName('article:view', 'web');

        expect($exception->getMessage())->toContain('article:view')
            ->and($exception->getMessage())->toContain('web');
    });

    it('creates exception for id lookup', function () {
        $exception = PermissionNotFoundException::withId(123, 'web');

        expect($exception->getMessage())->toContain('123')
            ->and($exception->getMessage())->toContain('web');
    });
});

describe('RoleAlreadyExistsException', function () {
    it('includes role name and guard in message', function () {
        $exception = RoleAlreadyExistsException::create('admin', 'web');

        expect($exception->getMessage())->toContain('admin')
            ->and($exception->getMessage())->toContain('web');
    });
});

describe('RoleNotFoundException', function () {
    it('creates exception for name lookup', function () {
        $exception = RoleNotFoundException::withName('admin', 'web');

        expect($exception->getMessage())->toContain('admin')
            ->and($exception->getMessage())->toContain('web');
    });

    it('creates exception for id lookup', function () {
        $exception = RoleNotFoundException::withId(123, 'web');

        expect($exception->getMessage())->toContain('123')
            ->and($exception->getMessage())->toContain('web');
    });
});

describe('UnauthorizedException', function () {
    it('returns 403 status code', function () {
        $exception = new UnauthorizedException;

        expect($exception->getStatusCode())->toBe(403);
    });

    describe('factory methods', function () {
        it('creates exception for a single missing role', function () {
            $exception = UnauthorizedException::forRole('admin');

            expect($exception->requiredRoles)->toBe(['admin'])
                ->and($exception->requiredPermissions)->toBe([])
                ->and($exception->getMessage())->toContain('admin');
        });

        it('creates exception for missing roles', function () {
            $exception = UnauthorizedException::forRoles(['admin', 'editor']);

            expect($exception->requiredRoles)->toBe(['admin', 'editor'])
                ->and($exception->requiredPermissions)->toBe([])
                ->and($exception->getMessage())->toContain('admin, editor');
        });

        it('uses singular method for single role array', function () {
            $exception = UnauthorizedException::forRoles(['admin']);

            expect($exception->requiredRoles)->toBe(['admin'])
                ->and($exception->getMessage())->toContain('admin');
        });

        it('creates exception for a single missing permission', function () {
            $exception = UnauthorizedException::forPermission('article:edit');

            expect($exception->requiredPermissions)->toBe(['article:edit'])
                ->and($exception->requiredRoles)->toBe([])
                ->and($exception->getMessage())->toContain('article:edit');
        });

        it('creates exception for missing permissions', function () {
            $exception = UnauthorizedException::forPermissions(['article:view', 'article:edit']);

            expect($exception->requiredPermissions)->toBe(['article:view', 'article:edit'])
                ->and($exception->requiredRoles)->toBe([])
                ->and($exception->getMessage())->toContain('article:view, article:edit');
        });

        it('uses singular method for single permission array', function () {
            $exception = UnauthorizedException::forPermissions(['article:edit']);

            expect($exception->requiredPermissions)->toBe(['article:edit'])
                ->and($exception->getMessage())->toContain('article:edit');
        });

        it('creates exception for missing role or permission', function () {
            $exception = UnauthorizedException::forRolesOrPermissions(
                ['admin'],
                ['article:edit']
            );

            expect($exception->requiredRoles)->toBe(['admin'])
                ->and($exception->requiredPermissions)->toBe(['article:edit']);
        });

        it('creates exception for unauthenticated user', function () {
            $exception = UnauthorizedException::notLoggedIn();

            expect($exception->getMessage())->toContain('must be logged in');
        });

        it('creates exception for non-eloquent model', function () {
            $exception = UnauthorizedException::notEloquentModel();

            expect($exception->getMessage())->toContain('Eloquent');
        });
    });

    describe('message placeholders', function () {
        it('replaces :role placeholder', function () {
            $exception = UnauthorizedException::forRole('admin');

            expect($exception->getMessage())->toContain('admin');
        });

        it('replaces :roles placeholder', function () {
            $exception = UnauthorizedException::forRoles(['admin', 'editor']);

            expect($exception->getMessage())->toContain('admin, editor');
        });

        it('replaces :permission placeholder', function () {
            $exception = UnauthorizedException::forPermission('article:edit');

            expect($exception->getMessage())->toContain('article:edit');
        });

        it('replaces :permissions placeholder', function () {
            $exception = UnauthorizedException::forPermissions(['view', 'edit']);

            expect($exception->getMessage())->toContain('view, edit');
        });
    });

    describe('message customization', function () {
        it('uses translation file messages with placeholders', function () {
            // The package loads translations with placeholders
            $exception = UnauthorizedException::forRole('admin');

            // Translation file has ":role" placeholder which gets replaced
            expect($exception->getMessage())->toContain('admin')
                ->and($exception->getMessage())->toContain('role');
        });

        it('replaces :role placeholder with role name', function () {
            $exception = UnauthorizedException::forRole('administrator');

            expect($exception->getMessage())->toContain('administrator');
        });

        it('replaces :roles placeholder with comma-separated names', function () {
            $exception = UnauthorizedException::forRoles(['admin', 'super']);

            expect($exception->getMessage())->toContain('admin, super');
        });

        it('replaces :permission placeholder with permission name', function () {
            $exception = UnauthorizedException::forPermission('users:delete');

            expect($exception->getMessage())->toContain('users:delete');
        });

        it('replaces :permissions placeholder with comma-separated names', function () {
            $exception = UnauthorizedException::forPermissions(['users:create', 'users:delete']);

            expect($exception->getMessage())->toContain('users:create, users:delete');
        });

        it('stores required roles and permissions for custom handling', function () {
            $exception = UnauthorizedException::forRolesOrPermissions(
                ['admin', 'super'],
                ['users:manage']
            );

            expect($exception->requiredRoles)->toBe(['admin', 'super'])
                ->and($exception->requiredPermissions)->toBe(['users:manage']);
        });

        it('returns default message when translation key not found', function () {
            // notEloquentModel uses a message that should resolve
            $exception = UnauthorizedException::notEloquentModel();

            expect($exception->getMessage())->toBe('Not an Eloquent model.');
        });

        it('returns default not logged in message', function () {
            $exception = UnauthorizedException::notLoggedIn();

            expect($exception->getMessage())->toBe('You must be logged in.');
        });
    });
});
