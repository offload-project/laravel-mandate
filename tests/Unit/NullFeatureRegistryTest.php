<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use OffloadProject\Mandate\Services\NullFeatureRegistry;

test('all returns empty collection', function () {
    $registry = new NullFeatureRegistry();

    expect($registry->all())->toBeEmpty();
});

test('forModel returns empty collection', function () {
    $registry = new NullFeatureRegistry();
    $user = new User();

    expect($registry->forModel($user))->toBeEmpty();
});

test('find returns null', function () {
    $registry = new NullFeatureRegistry();

    expect($registry->find('SomeFeature'))->toBeNull();
});

test('permissions returns empty collection', function () {
    $registry = new NullFeatureRegistry();

    expect($registry->permissions('SomeFeature'))->toBeEmpty();
});

test('roles returns empty collection', function () {
    $registry = new NullFeatureRegistry();

    expect($registry->roles('SomeFeature'))->toBeEmpty();
});

test('isActive returns false', function () {
    $registry = new NullFeatureRegistry();
    $user = new User();

    expect($registry->isActive($user, 'SomeFeature'))->toBeFalse();
});

test('clearCache does nothing without error', function () {
    $registry = new NullFeatureRegistry();

    // Should not throw any errors
    $registry->clearCache();

    expect(true)->toBeTrue();
});
