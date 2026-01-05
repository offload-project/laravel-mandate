<?php

declare(strict_types=1);

use OffloadProject\Mandate\Attributes\Context;
use OffloadProject\Mandate\Attributes\FeatureSet;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Scope;

describe('Context Attribute', function () {
    test('it stores context name', function () {
        $context = new Context('team');

        expect($context->name)->toBe('team');
        expect($context->modelType)->toBeNull();
    });

    test('it stores context name with model type', function () {
        $context = new Context('tenant', 'App\\Models\\Team');

        expect($context->name)->toBe('tenant');
        expect($context->modelType)->toBe('App\\Models\\Team');
    });

    test('it can be applied to class constants', function () {
        $class = new #[Context('team')] class
        {
            #[Context('tenant', 'App\\Models\\Team')]
            public const MANAGE = 'manage';
        };

        $classReflection = new ReflectionClass($class);
        $classAttributes = $classReflection->getAttributes(Context::class);

        expect($classAttributes)->toHaveCount(1);
        expect($classAttributes[0]->newInstance()->name)->toBe('team');

        $constantReflection = new ReflectionClassConstant($class, 'MANAGE');
        $constantAttributes = $constantReflection->getAttributes(Context::class);

        expect($constantAttributes)->toHaveCount(1);
        expect($constantAttributes[0]->newInstance()->name)->toBe('tenant');
        expect($constantAttributes[0]->newInstance()->modelType)->toBe('App\\Models\\Team');
    });
});

describe('FeatureSet Attribute', function () {
    test('it stores feature set name', function () {
        $featureSet = new FeatureSet('billing');

        expect($featureSet->name)->toBe('billing');
        expect($featureSet->label)->toBeNull();
    });

    test('it stores feature set name with label', function () {
        $featureSet = new FeatureSet('billing', 'Billing Features');

        expect($featureSet->name)->toBe('billing');
        expect($featureSet->label)->toBe('Billing Features');
    });

    test('it can be applied to classes', function () {
        $class = new #[FeatureSet('billing', 'Billing Features')] class
        {
            public const INVOICES = 'invoices';
        };

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(FeatureSet::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->name)->toBe('billing');
        expect($attributes[0]->newInstance()->label)->toBe('Billing Features');
    });
});

describe('Guard Attribute', function () {
    test('it stores guard name', function () {
        $guard = new Guard('api');

        expect($guard->name)->toBe('api');
    });

    test('it can be applied to class and constants', function () {
        $class = new #[Guard('web')] class
        {
            #[Guard('api')]
            public const API_PERMISSION = 'api.access';
        };

        $classReflection = new ReflectionClass($class);
        $classAttributes = $classReflection->getAttributes(Guard::class);

        expect($classAttributes)->toHaveCount(1);
        expect($classAttributes[0]->newInstance()->name)->toBe('web');

        $constantReflection = new ReflectionClassConstant($class, 'API_PERMISSION');
        $constantAttributes = $constantReflection->getAttributes(Guard::class);

        expect($constantAttributes)->toHaveCount(1);
        expect($constantAttributes[0]->newInstance()->name)->toBe('api');
    });
});

describe('Scope Attribute', function () {
    test('it stores scope name', function () {
        $scope = new Scope('feature');

        expect($scope->name)->toBe('feature');
    });

    test('it can be applied to class and constants', function () {
        $class = new #[Scope('team')] class
        {
            #[Scope('feature')]
            public const BETA_ACCESS = 'beta.access';
        };

        $classReflection = new ReflectionClass($class);
        $classAttributes = $classReflection->getAttributes(Scope::class);

        expect($classAttributes)->toHaveCount(1);
        expect($classAttributes[0]->newInstance()->name)->toBe('team');

        $constantReflection = new ReflectionClassConstant($class, 'BETA_ACCESS');
        $constantAttributes = $constantReflection->getAttributes(Scope::class);

        expect($constantAttributes)->toHaveCount(1);
        expect($constantAttributes[0]->newInstance()->name)->toBe('feature');
    });
});
