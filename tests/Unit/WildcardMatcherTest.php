<?php

declare(strict_types=1);

use OffloadProject\Mandate\Support\WildcardMatcher;

beforeEach(function () {
    WildcardMatcher::clearCache();
});

test('isWildcard detects wildcard patterns', function () {
    expect(WildcardMatcher::isWildcard('users.*'))->toBeTrue();
    expect(WildcardMatcher::isWildcard('*.view'))->toBeTrue();
    expect(WildcardMatcher::isWildcard('*'))->toBeTrue();
    expect(WildcardMatcher::isWildcard('users.*.view'))->toBeTrue();
});

test('isWildcard returns false for non-wildcard strings', function () {
    expect(WildcardMatcher::isWildcard('users.view'))->toBeFalse();
    expect(WildcardMatcher::isWildcard('view users'))->toBeFalse();
    expect(WildcardMatcher::isWildcard(''))->toBeFalse();
});

test('matches handles exact matches', function () {
    expect(WildcardMatcher::matches('users.view', 'users.view'))->toBeTrue();
    expect(WildcardMatcher::matches('users.view', 'users.create'))->toBeFalse();
});

test('matches prefix wildcards correctly', function () {
    // users.* should match users.view, users.create, etc.
    expect(WildcardMatcher::matches('users.*', 'users.view'))->toBeTrue();
    expect(WildcardMatcher::matches('users.*', 'users.create'))->toBeTrue();
    expect(WildcardMatcher::matches('users.*', 'users.delete'))->toBeTrue();

    // Should NOT match other namespaces
    expect(WildcardMatcher::matches('users.*', 'posts.view'))->toBeFalse();
    expect(WildcardMatcher::matches('users.*', 'admin.users.view'))->toBeFalse();

    // Should NOT match nested segments (single segment only)
    expect(WildcardMatcher::matches('users.*', 'users.admin.view'))->toBeFalse();
});

test('matches suffix wildcards correctly', function () {
    // *.view should match users.view, posts.view, etc.
    expect(WildcardMatcher::matches('*.view', 'users.view'))->toBeTrue();
    expect(WildcardMatcher::matches('*.view', 'posts.view'))->toBeTrue();
    expect(WildcardMatcher::matches('*.view', 'reports.view'))->toBeTrue();

    // Should NOT match other suffixes
    expect(WildcardMatcher::matches('*.view', 'users.create'))->toBeFalse();
    expect(WildcardMatcher::matches('*.view', 'posts.delete'))->toBeFalse();

    // Should NOT match nested segments
    expect(WildcardMatcher::matches('*.view', 'admin.users.view'))->toBeFalse();
});

test('matches single wildcard', function () {
    // Single * matches any single-segment permission
    expect(WildcardMatcher::matches('*', 'view'))->toBeTrue();
    expect(WildcardMatcher::matches('*', 'admin'))->toBeTrue();

    // Should NOT match multi-segment permissions
    expect(WildcardMatcher::matches('*', 'users.view'))->toBeFalse();
});

test('matches middle wildcards', function () {
    // users.*.view should match users.admin.view, users.public.view
    expect(WildcardMatcher::matches('users.*.view', 'users.admin.view'))->toBeTrue();
    expect(WildcardMatcher::matches('users.*.view', 'users.public.view'))->toBeTrue();

    // Should NOT match wrong prefix/suffix
    expect(WildcardMatcher::matches('users.*.view', 'posts.admin.view'))->toBeFalse();
    expect(WildcardMatcher::matches('users.*.view', 'users.admin.edit'))->toBeFalse();
});

test('expand returns matching permissions for prefix wildcard', function () {
    $all = ['users.view', 'users.create', 'users.delete', 'posts.view', 'posts.create'];

    $expanded = WildcardMatcher::expand('users.*', $all);

    expect($expanded)->toBe(['users.view', 'users.create', 'users.delete']);
});

test('expand returns matching permissions for suffix wildcard', function () {
    $all = ['users.view', 'users.create', 'posts.view', 'posts.create', 'reports.view'];

    $expanded = WildcardMatcher::expand('*.view', $all);

    expect($expanded)->toBe(['users.view', 'posts.view', 'reports.view']);
});

test('expand returns empty array when no matches', function () {
    $all = ['users.view', 'users.create', 'posts.view'];

    $expanded = WildcardMatcher::expand('admin.*', $all);

    expect($expanded)->toBe([]);
});

test('expand returns exact match for non-wildcard if exists', function () {
    $all = ['users.view', 'users.create', 'posts.view'];

    expect(WildcardMatcher::expand('users.view', $all))->toBe(['users.view']);
    expect(WildcardMatcher::expand('nonexistent', $all))->toBe([]);
});

test('clearCache clears the pattern cache', function () {
    // Call matches to populate cache
    WildcardMatcher::matches('users.*', 'users.view');
    WildcardMatcher::matches('*.view', 'posts.view');

    // Clear cache
    WildcardMatcher::clearCache();

    // Should still work after clearing
    expect(WildcardMatcher::matches('users.*', 'users.view'))->toBeTrue();
});

test('matches handles special regex characters in permission names', function () {
    // Permission names with special characters should be escaped
    expect(WildcardMatcher::matches('users.*', 'users.view+edit'))->toBeTrue();
    expect(WildcardMatcher::matches('feature[beta].*', 'feature[beta].test'))->toBeTrue();
});

test('matches works with space-separated permission format', function () {
    // Wildcards also work with space-separated permissions like "view users"
    // The * matches any word (non-dot characters) so it works with spaces too
    expect(WildcardMatcher::matches('view *', 'view users'))->toBeTrue();
    expect(WildcardMatcher::matches('view *', 'view posts'))->toBeTrue();
    expect(WildcardMatcher::matches('* users', 'view users'))->toBeTrue();
    expect(WildcardMatcher::matches('* users', 'delete users'))->toBeTrue();
});
