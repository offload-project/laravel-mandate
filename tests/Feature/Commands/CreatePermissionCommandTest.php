<?php

declare(strict_types=1);

use OffloadProject\Mandate\Models\Permission;

describe('CreatePermissionCommand', function () {
    it('creates a permission', function () {
        $this->artisan('mandate:permission', [
            'name' => 'article:view',
        ])
            ->assertSuccessful();

        expect(Permission::where('name', 'article:view')->exists())->toBeTrue();
    });

    it('creates a permission with specified guard', function () {
        $this->artisan('mandate:permission', [
            'name' => 'article:view',
            '--guard' => 'api',
        ])
            ->expectsOutputToContain('api')
            ->assertSuccessful();

        expect(Permission::where('name', 'article:view')->where('guard', 'api')->exists())->toBeTrue();
    });

    it('warns when permission already exists', function () {
        Permission::create(['name' => 'article:view', 'guard' => 'web']);

        $this->artisan('mandate:permission', [
            'name' => 'article:view',
        ])
            ->expectsOutputToContain('already exists')
            ->assertSuccessful();
    });
});
