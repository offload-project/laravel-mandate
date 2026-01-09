<?php

declare(strict_types=1);

use OffloadProject\Mandate\Attributes\Capability;
use OffloadProject\Mandate\Attributes\Context;
use OffloadProject\Mandate\Attributes\Description;
use OffloadProject\Mandate\Attributes\Guard;
use OffloadProject\Mandate\Attributes\Label;

describe('Attributes', function () {
    describe('Label', function () {
        it('stores the value', function () {
            $label = new Label('My Label');

            expect($label->value)->toBe('My Label');
        });
    });

    describe('Description', function () {
        it('stores the value', function () {
            $description = new Description('My Description');

            expect($description->value)->toBe('My Description');
        });
    });

    describe('Guard', function () {
        it('stores the name', function () {
            $guard = new Guard('api');

            expect($guard->name)->toBe('api');
        });
    });

    describe('Context', function () {
        it('stores the model class', function () {
            $context = new Context('App\\Models\\Team');

            expect($context->modelClass)->toBe('App\\Models\\Team');
        });
    });

    describe('Capability', function () {
        it('stores the name', function () {
            $capability = new Capability('content-management');

            expect($capability->name)->toBe('content-management');
        });

        it('is repeatable', function () {
            $reflection = new ReflectionClass(Capability::class);
            $attributes = $reflection->getAttributes(Attribute::class);

            expect($attributes)->toHaveCount(1);

            $attr = $attributes[0]->newInstance();
            expect($attr->flags & Attribute::IS_REPEATABLE)->toBeTruthy();
        });
    });
});
