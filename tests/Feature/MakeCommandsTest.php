<?php

declare(strict_types=1);

use OffloadProject\Mandate\Console\Commands\FeatureMakeCommand;
use OffloadProject\Mandate\Console\Commands\PermissionMakeCommand;
use OffloadProject\Mandate\Console\Commands\RoleMakeCommand;

describe('mandate:feature command', function () {
    test('it is registered', function () {
        expect($this->app->make(FeatureMakeCommand::class))->toBeInstanceOf(FeatureMakeCommand::class);
    });

    test('it has correct signature', function () {
        $command = $this->app->make(FeatureMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command))->toContain('mandate:feature');
        expect($signature->getValue($command))->toContain('{name :');
        expect($signature->getValue($command))->toContain('--set=');
    });

    test('it has correct description', function () {
        $command = $this->app->make(FeatureMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $description = $reflection->getProperty('description');
        $description->setAccessible(true);

        expect($description->getValue($command))->toBe('Create a new feature class');
    });

    test('it uses correct stub path', function () {
        $command = $this->app->make(FeatureMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stub = $method->invoke($command);

        expect($stub)->toContain('stubs/feature.stub');
        expect(file_exists($stub))->toBeTrue();
    });
});

describe('mandate:permission command', function () {
    test('it is registered', function () {
        expect($this->app->make(PermissionMakeCommand::class))->toBeInstanceOf(PermissionMakeCommand::class);
    });

    test('it has correct signature', function () {
        $command = $this->app->make(PermissionMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command))->toContain('mandate:permission');
        expect($signature->getValue($command))->toContain('{name :');
        expect($signature->getValue($command))->toContain('--set=');
    });

    test('it has correct description', function () {
        $command = $this->app->make(PermissionMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $description = $reflection->getProperty('description');
        $description->setAccessible(true);

        expect($description->getValue($command))->toBe('Create a new permission class');
    });

    test('it uses correct stub path', function () {
        $command = $this->app->make(PermissionMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stub = $method->invoke($command);

        expect($stub)->toContain('stubs/permission.stub');
        expect(file_exists($stub))->toBeTrue();
    });
});

describe('mandate:role command', function () {
    test('it is registered', function () {
        expect($this->app->make(RoleMakeCommand::class))->toBeInstanceOf(RoleMakeCommand::class);
    });

    test('it has correct signature', function () {
        $command = $this->app->make(RoleMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command))->toContain('mandate:role');
        expect($signature->getValue($command))->toContain('{name :');
        expect($signature->getValue($command))->toContain('--set=');
    });

    test('it has correct description', function () {
        $command = $this->app->make(RoleMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $description = $reflection->getProperty('description');
        $description->setAccessible(true);

        expect($description->getValue($command))->toBe('Create a new role class');
    });

    test('it uses correct stub path', function () {
        $command = $this->app->make(RoleMakeCommand::class);
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stub = $method->invoke($command);

        expect($stub)->toContain('stubs/role.stub');
        expect(file_exists($stub))->toBeTrue();
    });
});
