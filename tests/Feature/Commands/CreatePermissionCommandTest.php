<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;

describe('mandate:permission --db', function () {
    it('creates a permission in database', function () {
        $this->artisan('mandate:permission', [
            'name' => 'article:view',
            '--db' => true,
        ])
            ->assertSuccessful();

        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
    });

    it('creates a permission with specified guard', function () {
        $this->artisan('mandate:permission', [
            'name' => 'article:view',
            '--guard' => 'api',
            '--db' => true,
        ])
            ->expectsOutputToContain('api')
            ->assertSuccessful();

        expect(Permission::where('name', 'article:view')->where('guard', 'api')->exists())->toBeTrue();
    });

    it('warns when permission already exists', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $this->artisan('mandate:permission', [
            'name' => 'article:view',
            '--db' => true,
        ])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();
    });
});
