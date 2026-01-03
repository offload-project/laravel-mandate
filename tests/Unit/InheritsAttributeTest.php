<?php

declare(strict_types=1);

use OffloadProject\Mandate\Attributes\Inherits;
use OffloadProject\Mandate\Tests\Fixtures\Roles\TestRolesForAttribute;

test('it stores single parent role', function () {
    $inherits = new Inherits('viewer');

    expect($inherits->parents)->toBe(['viewer']);
});

test('it stores multiple parent roles', function () {
    $inherits = new Inherits('admin', 'billing-admin');

    expect($inherits->parents)->toBe(['admin', 'billing-admin']);
});

test('it stores empty parents when no arguments', function () {
    $inherits = new Inherits();

    expect($inherits->parents)->toBe([]);
});

test('it can be applied to class constants', function () {
    $reflection = new ReflectionClassConstant(TestRolesForAttribute::class, 'EDITOR');
    $attributes = $reflection->getAttributes(Inherits::class);

    expect($attributes)->toHaveCount(1);
    expect($attributes[0]->newInstance()->parents)->toBe(['viewer']);
});
