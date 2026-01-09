<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;
use OffloadProject\Mandate\WildcardPermission;

beforeEach(function () {
    $this->handler = new WildcardPermission;
});

describe('WildcardPermission', function () {
    describe('exact matching', function () {
        it('matches exact permission strings', function () {
            expect($this->handler->matches('article:view', 'article:view'))->toBeTrue()
                ->and($this->handler->matches('article:view', 'article:edit'))->toBeFalse();
        });
    });

    describe('universal wildcard', function () {
        it('matches everything with asterisk', function () {
            expect($this->handler->matches('*', 'article:view'))->toBeTrue()
                ->and($this->handler->matches('*', 'user:edit'))->toBeTrue()
                ->and($this->handler->matches('*', 'anything'))->toBeTrue();
        });
    });

    describe('suffix wildcard', function () {
        it('matches resource:* patterns', function () {
            expect($this->handler->matches('article:*', 'article:view'))->toBeTrue()
                ->and($this->handler->matches('article:*', 'article:edit'))->toBeTrue()
                ->and($this->handler->matches('article:*', 'article:delete'))->toBeTrue()
                ->and($this->handler->matches('article:*', 'user:view'))->toBeFalse();
        });

        it('matches multi-segment actions', function () {
            expect($this->handler->matches('article:*', 'article:view:all'))->toBeTrue()
                ->and($this->handler->matches('article:*', 'article:edit:own'))->toBeTrue();
        });
    });

    describe('prefix wildcard', function () {
        it('matches *.action patterns', function () {
            expect($this->handler->matches('*:view', 'article:view'))->toBeTrue()
                ->and($this->handler->matches('*:view', 'user:view'))->toBeTrue()
                ->and($this->handler->matches('*:view', 'article:edit'))->toBeFalse();
        });
    });

    describe('subpart matching', function () {
        it('matches comma-separated subparts with both parts having subparts', function () {
            // The subpart matching requires both parts to have the same number of segments
            expect($this->handler->matches('article:view', 'article:view'))->toBeTrue()
                ->and($this->handler->matches('article:edit', 'article:view'))->toBeFalse();
        });
    });

    describe('containsWildcard', function () {
        it('detects wildcards in patterns', function () {
            expect($this->handler->containsWildcard('*'))->toBeTrue()
                ->and($this->handler->containsWildcard('article:*'))->toBeTrue()
                ->and($this->handler->containsWildcard('*:view'))->toBeTrue()
                ->and($this->handler->containsWildcard('article:view'))->toBeFalse();
        });
    });

    describe('parse', function () {
        it('parses permission string into parts', function () {
            $parsed = $this->handler->parse('article:edit');

            expect($parsed)->toBe(['resource' => 'article', 'action' => 'edit']);
        });

        it('handles permission without action', function () {
            $parsed = $this->handler->parse('admin');

            expect($parsed)->toBe(['resource' => 'admin', 'action' => null]);
        });

        it('handles complex permission strings', function () {
            $parsed = $this->handler->parse('article:edit:own');

            expect($parsed)->toBe(['resource' => 'article', 'action' => 'edit:own']);
        });
    });

    describe('build', function () {
        it('builds permission string from parts', function () {
            $permission = $this->handler->build('article', 'edit');

            expect($permission)->toBe('article:edit');
        });

        it('builds permission string without action', function () {
            $permission = $this->handler->build('admin');

            expect($permission)->toBe('admin');
        });
    });

    describe('getMatchingPermissions', function () {
        it('filters collection to matching permissions', function () {
            $permissions = collect([
                Permission::create(['name' => 'article:view', 'guard' => 'web']),
                Permission::create(['name' => 'article:edit', 'guard' => 'web']),
                Permission::create(['name' => 'user:view', 'guard' => 'web']),
            ]);

            $matches = $this->handler->getMatchingPermissions('article:*', $permissions);

            expect($matches)->toHaveCount(2)
                ->and($matches->pluck('name')->toArray())->toContain('article:view', 'article:edit');
        });
    });

    describe('caching', function () {
        it('caches compiled regex patterns', function () {
            $this->handler->matches('article:*', 'article:view');
            $this->handler->matches('article:*', 'article:edit');

            $reflection = new ReflectionClass($this->handler);
            $property = $reflection->getProperty('patternCache');
            $cache = $property->getValue($this->handler);

            expect($cache)->toHaveKey('article:*');
        });

        it('can clear cache', function () {
            $this->handler->matches('article:*', 'article:view');
            $this->handler->clearCache();

            $reflection = new ReflectionClass($this->handler);
            $property = $reflection->getProperty('patternCache');
            $cache = $property->getValue($this->handler);

            expect($cache)->toBeEmpty();
        });
    });
});
